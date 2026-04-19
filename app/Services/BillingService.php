<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Business;
use App\Models\SaleDetails;
use App\Models\Party;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BillingService
{
    protected $apiUrl;
    protected $apiKey;
    protected $jwtSecret;
    protected $mode;
    protected $repoEnv;
    protected $iambOverride;
    protected $denvFeOverride;
    protected $httpConnectTimeout;
    protected $httpTimeout;

    /**
     * Remove null values from nested payloads to avoid sending nullable keys
     * that some external validators treat as invalid required fields.
     */
    protected function removeNullValues($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $cleaned = [];
        foreach ($value as $key => $item) {
            $item = $this->removeNullValues($item);
            if ($item === null) {
                continue;
            }
            $cleaned[$key] = $item;
        }

        return $cleaned;
    }

    protected function resolveApiKeyForSale(Sale $sale): ?string
    {
        $business = Business::select('id', 'emagic_api_key')->find($sale->business_id);
        if (!empty($business?->emagic_api_key)) {
            return $business->emagic_api_key;
        }

        return $this->apiKey;
    }

    protected function buildCompatibilityPayload(array $payload): array
    {
        // Some eMagic validations treat nullable fields as required.
        if (isset($payload['gdgen']) && is_array($payload['gdgen'])) {
            $payload['gdgen']['dseg'] = $payload['gdgen']['dseg'] ?? '0.00';

            $idest = (int) ($payload['gdgen']['idest'] ?? 1);
            if ($idest === 2) {
                $payload['gdgen']['gfexp'] = $payload['gdgen']['gfexp'] ?? (object) [];
            } else {
                unset($payload['gdgen']['gfexp']);
            }

            $idoc = (string) ($payload['gdgen']['idoc'] ?? '01');
            if (in_array($idoc, ['04', '05'], true)) {
                $payload['gdgen']['gdfref'] = $payload['gdgen']['gdfref'] ?? [];
            } else {
                unset($payload['gdgen']['gdfref']);
            }
        }

        if (isset($payload['gtot']['gformaPago'][0]) && is_array($payload['gtot']['gformaPago'][0])) {
            $formaPago = (string) ($payload['gtot']['gformaPago'][0]['iformaPago'] ?? '');
            if ($formaPago === '99') {
                $payload['gtot']['gformaPago'][0]['dformaPagoDesc'] = $payload['gtot']['gformaPago'][0]['dformaPagoDesc'] ?? 'OTROS';
            } else {
                unset($payload['gtot']['gformaPago'][0]['dformaPagoDesc']);
            }
        }

        if (isset($payload['gitem']) && is_array($payload['gitem'])) {
            foreach ($payload['gitem'] as $idx => $item) {
                if (is_array($item) && (!array_key_exists('cunidadCPBS', $item) || $item['cunidadCPBS'] === null)) {
                    $payload['gitem'][$idx]['cunidadCPBS'] = 'UND';
                }
            }
        }

        return $payload;
    }
    protected function isLiveMode(): bool
    {
        $mode = strtolower((string) $this->mode);
        return in_array($mode, ['live', 'prod', 'production'], true);
    }

    protected function getRepoEnvironment(): string
    {
        $repoEnv = strtolower((string) $this->repoEnv);
        if (in_array($repoEnv, ['live', 'test'], true)) {
            return $repoEnv;
        }

        return $this->isLiveMode() ? 'live' : 'test';
    }

    public function __construct()
    {
        $this->apiUrl = config('billing.api_url', 'https://api.facturacion.example.com');
        $this->apiKey = config('billing.api_key');
        $this->jwtSecret = config('billing.jwt_secret');
        $this->mode = config('billing.mode', 'test');
        $this->repoEnv = config('billing.repo_env');
        $this->iambOverride = config('billing.iamb');
        $this->denvFeOverride = config('billing.denv_fe');
        $this->httpConnectTimeout = (int) config('billing.http_connect_timeout', 5);
        $this->httpTimeout = (int) config('billing.http_timeout', 15);
    }

    /**
     * Send sale data to external billing service
     * 
     * @param Sale $sale
     * @return array
     */
    public function sendSaleToExternalBilling(Sale $sale, $party = null)
    {
        try {
            // eMagic validation is schema-sensitive; keep explicit nullable keys
            // instead of dropping them, to preserve the expected payload shape.
            $jsonData = $this->formatSaleDataForBilling($sale, $party);
            $apiKey = $this->resolveApiKeyForSale($sale);

            if (empty($apiKey)) {
                return [
                    'success' => false,
                    'message' => 'Missing EMAGIC API key for business',
                    'error' => 'missing_emagic_api_key'
                ];
            }
            
                        // Send data to external billing API with EMAGIC_API_KEY header
                        $response = Http::withHeaders([
                                'Ocp-Apim-Subscription-Key' => $apiKey
                        ])->connectTimeout($this->httpConnectTimeout)
                            ->timeout($this->httpTimeout)
                            ->post($this->apiUrl."/facturar/v1.1/autorizar", $jsonData);

            // Debug log for API request and response
            Log::debug('Billing API Request', [
                'sale_id' => $sale->id,
                'url' => $this->apiUrl,
                'request_data' => $jsonData
            ]);

            Log::debug('Billing API Response', [
                'sale_id' => $sale->id,
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->json()
            ]);

            // Retry once with compatibility defaults when provider reports null required fields.
            if (!$response->successful()) {
                $responseBody = (string) $response->body();
                if (stripos($responseBody, 'campos obligatorios como nulos') !== false) {
                    $compatPayload = $this->buildCompatibilityPayload($jsonData);

                    Log::warning('Billing API Retry with compatibility payload', [
                        'sale_id' => $sale->id,
                    ]);

                    $response = Http::withHeaders([
                        'Ocp-Apim-Subscription-Key' => $apiKey
                    ])->connectTimeout($this->httpConnectTimeout)
                      ->timeout($this->httpTimeout)
                      ->post($this->apiUrl."/facturar/v1.1/autorizar", $compatPayload);

                    Log::debug('Billing API Retry Response', [
                        'sale_id' => $sale->id,
                        'status' => $response->status(),
                        'headers' => $response->headers(),
                        'body' => $response->json()
                    ]);
                }
            }
            
            if ($response->successful()) {
                // Get the response data
                $responseData = $response->json();
                
                // Extract dId from xmlFirmado if available
                $xmlContent = $responseData['xmlFirmado'] ?? null;
                $dgiInvoiceId = null;
                
                if ($xmlContent) {
                    // Load XML string and extract dId
                    $xml = simplexml_load_string($xmlContent);
                    $dgiInvoiceId = (string) $xml->dId;
                }
                
                // Save to dgi_invoice table
                DB::table('dgi_invoice')->insert([
                    'dgi_invoice_id' => $dgiInvoiceId,
                    'xml_response' => $xmlContent,
                    'sale_id' => $sale->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // Update sale record
                $sale->update([
                    'meta' => array_merge($sale->meta ?? [], [
                        'billing_status' => 'success',
                        'billing_date' => now()->toDateTimeString()
                    ])
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Invoice generated successfully',
                    'data' => $response->json()
                ];
            } else {
                // Log the error
                Log::error('Billing API Error', [
                    'sale_id' => $sale->id,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                
                // Save the error to the sale record
                $sale->update([
                    'meta' => array_merge($sale->meta ?? [], [
                        'billing_error' => $response->body(),
                        'billing_status' => 'error',
                        'billing_date' => now()->toDateTimeString()
                    ])
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to generate invoice',
                    'error' => $response->body()
                ];
            }
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Billing Service Exception', [
                'sale_id' => $sale->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Save the error to the sale record
            $sale->update([
                'meta' => array_merge($sale->meta ?? [], [
                    'billing_error' => $e->getMessage(),
                    'billing_status' => 'exception',
                    'billing_date' => now()->toDateTimeString()
                ])
            ]);
            
            return [
                'success' => false,
                'message' => 'Exception occurred while generating invoice',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Format sale data according to the external billing service requirements
     * 
     * @param Sale $sale
     * @return array
     */
    protected function formatSaleDataForBilling(Sale $sale, $party = null)
    {
        $isLiveMode = $this->isLiveMode();
        $iamb = in_array((int) $this->iambOverride, [1, 2], true) ? (int) $this->iambOverride : ($isLiveMode ? 1 : 2);
        $denvFe = in_array((int) $this->denvFeOverride, [1, 2], true) ? (int) $this->denvFeOverride : $iamb;

        // Get the business and its invoice data
        $business = Business::with('invoice_data')->findOrFail($sale->business_id);
        $invoiceData = $business->invoice_data;
        
        // Get the customer/party data
        $party = $sale->party_id ? Party::findOrFail($sale->party_id) : $party;
        $partyInvoiceData = $party ? $sale->party_id ? $party->invoice_data : $party["invoice_data"] : null;
        // Get the sale details with products
        $saleDetails = SaleDetails::with('product')->where('sale_id', $sale->id)->get();
        $invoiceNumber = explode('-', $sale->invoiceNumber);
        $invoiceNumber = $invoiceNumber[1];
        // convert $partyInvoiceData from array to object with properties
        $partyInvoiceData = (object) $partyInvoiceData;
        Log::debug('Invoice', [
            'invoiceData' => $invoiceData,
            'partyInvoiceData' => $partyInvoiceData,
            'billing_env' => [
                'mode' => $this->mode,
                'iamb' => $iamb,
                'denvFE' => $denvFe,
                'repo_env' => $this->getRepoEnvironment(),
            ]
        ]);
        $receiverType = $partyInvoiceData->itipoRec ?? '02';
        $isDomesticReceiver = in_array((string) $receiverType, ['01', '03'], true);
        $documentType = '01';
        $destinationType = 1; // 1=Panama, 2=Exterior

        $paymentMethod = $sale->paymentType == 'Cash' ? '02' : '01'; // PDF v1.4: 01=credito, 02=efectivo
        $emissionDate = Carbon::parse($sale->saleDate ?? now(), 'America/Panama')->format('Y-m-d\TH:i:sP');

        $receiverData = [
            'itipoRec' => $receiverType,
            'cpaisRec' => 'PA',
            'dnombRec' => $partyInvoiceData->dnombRec ?? null,
        ];

        if ($partyInvoiceData && (($partyInvoiceData->itipoRec ?? '') == '01' || ($partyInvoiceData->itipoRec ?? '') == '03') && isset($partyInvoiceData->druc)) {
            $receiverData['grucRec'] = [
                'dtipoRuc' => isset($partyInvoiceData->dtipoRuc) ? ($partyInvoiceData->dtipoRuc == 'Natural' ? 1 : 2) : 1,
                'druc' => $partyInvoiceData->druc,
                'ddv' => $partyInvoiceData->ddv ?? null,
            ];
        } elseif ($partyInvoiceData && (empty($partyInvoiceData->dtipoRuc) || ($partyInvoiceData->dtipoRuc ?? '') == 'Jurídico') && isset($partyInvoiceData->druc)) {
            $receiverData['grucRec'] = [
                'dtipoRuc' => 2,
                'druc' => $partyInvoiceData->druc,
                'ddv' => $partyInvoiceData->ddv ?? null,
            ];
        } elseif ($partyInvoiceData && isset($partyInvoiceData->druc)) {
            $receiverData['grucRec'] = [
                'dtipoRuc' => 1,
                'druc' => $partyInvoiceData->druc,
            ];
        }

        if ((string) $receiverType === '04') {
            $receiverData['gidExt'] = [
                'didExt' => $partyInvoiceData->didExt ?? null,
                'dpaisExt' => $partyInvoiceData->dpaisExt ?? null,
            ];
        }

        if (isset($partyInvoiceData->dcorElectRec) && !empty($partyInvoiceData->dcorElectRec)) {
            $receiverData['dcorElectRec'] = [$partyInvoiceData->dcorElectRec];
        }

        if ($isDomesticReceiver) {
            $receiverData['ddirecRec'] = $partyInvoiceData->ddirecRec ?? null;
            if (isset($partyInvoiceData->dcodUbi)) {
                $receiverData['gubiRec'] = [
                    'dcodUbi' => $partyInvoiceData->dcodUbi,
                    'dcorreg' => $partyInvoiceData->dcorreg,
                    'dprov' => $partyInvoiceData->dprov,
                    'ddistr' => $partyInvoiceData->ddistr,
                ];
            }
        }

        $receiverData = $this->removeNullValues($receiverData);

        $formattedData = [
            'gitem' => [],
            'gdgen' => [
                'inatOp' => '01',
                'gemis' => [
                    'dsucEm' => '0000',
                    'dtfnEm' => isset($invoiceData->dtfnEm) ? [$invoiceData->dtfnEm] : null,
                    'dcoordEm' => $invoiceData->dcoordEm ?? '0,0',
                    'dcorElectEmi' => isset($invoiceData->dcorElectEmi) ? [$invoiceData->dcorElectEmi] : null,
                    'gubiEm' => [
                        'dcodUbi' => $invoiceData->dcodUbi ?? '4-13-2',
                        'dcorreg' => $invoiceData->dcorreg ?? 'Bella Vista',
                        'dprov' => $invoiceData->dprov ?? 'Panamá',
                        'ddistr' => $invoiceData->ddistr ?? 'Panamá'
                    ],
                    'dnombEm' => $isLiveMode ? ($invoiceData->dnombEm ?? $business->companyName) : "FE generada en ambiente de pruebas - sin valor comercial ni fiscal",
                    'ddirecEm' => $invoiceData->ddirecEm ?? $business->address,
                    'grucEmi' => [
                        'ddv' => $isLiveMode ? $invoiceData->ddv : "37",
                        'druc' => $isLiveMode ? $invoiceData->druc : "155705519-2-2021",
                        'dtipoRuc' => $isLiveMode ? ($invoiceData->dtipoRuc == "Natural" ? 1 : 2) : 2
                    ]
                ],
                'dseg' => '0.00',
                'itpEmis' => '01',
                'itipoSuc' => 1,
                'dptoFacDF' => str_pad($business->id, 3, '0', STR_PAD_LEFT),
                'iproGen' => 1,
                'denvFE' => $denvFe,
                'iformCAFE' => 1,
                'idest' => $destinationType,
                'gdatRec' => $receiverData,
                'itipoTranVenta' => 1,
                'itipoOp' => 1,
                'dnroDF' => str_pad((int)$invoiceNumber, 9, '0', STR_PAD_LEFT),
                'ientCAFE' => 1,
                'iamb' => $iamb,
                'dfechaEm' => $emissionDate,
                'idoc' => $documentType
            ],
            'dverForm' => '1.00',
            'gtot' => [
                'gformaPago' => [
                    [
                        'dvlrCuota' => number_format($sale->totalAmount, 2),
                        'iformaPago' => $paymentMethod
                    ]
                ],
                'dvtot' => number_format($sale->totalAmount, 2),
                'dtotITBMS' => number_format($sale->vat_amount ?? 0, 2),
                'dtotRec' => number_format($sale->totalAmount, 2),
                'dtotDesc' => number_format($sale->discountAmount ?? 0, 2),
                'dtotSeg' => '0.00',
                'dtotNeto' => number_format($sale->totalAmount - ($sale->vat_amount ?? 0), 2),
                'dtotAcar' => '0.00',
                'dvuelto' => '0.00',
                'dtotGravado' => number_format($sale->totalAmount - ($sale->vat_amount ?? 0), 2),
                'dnroItems' => count($saleDetails),
                'ipzPag' => 1,
                'dvtotItems' => number_format($sale->totalAmount, 2)
            ]
        ];

        if ($paymentMethod === '99') {
            $formattedData['gtot']['gformaPago'][0]['dformaPagoDesc'] = 'OTROS';
        }

        if ((float) ($sale->discountAmount ?? 0) > 0) {
            $formattedData['gtot']['gdescBonif'] = [
                [
                    'dDescProd' => 'DESCUENTO GENERAL',
                    'dValDesc' => number_format((float) $sale->discountAmount, 2),
                ],
            ];
        }

        if ($destinationType === 2) {
            $formattedData['gdgen']['gfexp'] = (object) [];
        }

        if (in_array($documentType, ['04', '05'], true)) {
            $formattedData['gdgen']['gdfref'] = [];
        }
        
        // Add items to the gitem array
        foreach ($saleDetails as $index => $detail) {
            $product = $detail->product;
            $quantity = max((float) $detail->quantities, 1.0);
            $lineSubtotal = (float) ($detail->subtotal ?? ((float) $detail->price * $quantity));
            $vatAmount = (float) ($detail->tax_amount ?? 0);
            $lineTotal = (float) ($detail->total ?? ($lineSubtotal + $vatAmount));
            $unitNetPrice = $lineSubtotal / $quantity;
            
            $formattedData['gitem'][] = [
                'gitbmsitem' => [
                    'dtasaITBMS' => '01', // Standard rate
                    'dvalITBMS' => number_format($vatAmount, 6)
                ],
                'dcodProd' => $product->productCode ?? ('PROD-' . $product->id),
                'cunidad' => 'UND',
                'ddescProd' => $product->productName,
                'dcantCodInt' => number_format($quantity, 2),
                'gprecios' => [
                    'dprAcarItem' => '0.00',
                    'dprSegItem' => '0.00',
                    'dprItem' => number_format($lineSubtotal, 6),
                    'dprUnit' => number_format($unitNetPrice, 6),
                    'dprUnitDesc' => '0.00',
                    'dvalTotItem' => number_format($lineTotal, 2)
                ],
                'cunidadCPBS' => 'UND',
                'dsecItem' => $index + 1
            ];
        }
        
        // Add purchase order information if available
        if (isset($sale->meta['purchase_order'])) {
            $formattedData['gpedComGl'] = [
                'dinfEmPedGl' => 'OC:' . $sale->meta['purchase_order'],
                'dnroPed' => (int) $sale->meta['purchase_order']
            ];
        }

        // Remove nullable keys to avoid strict validator rejections for null values.
        return $this->removeNullValues($formattedData);
    }

    /**
     * Get the Billing PDF url
     * 
     * @return string
     */
    public function getBillingPdfFile($saleId)
    {
        $sale = Sale::find($saleId);
        if (!$sale) {
            return null;
        }

        $apiKey = $this->resolveApiKeyForSale($sale);
        if (empty($apiKey)) {
            Log::warning('Billing PDF Error: missing EMAGIC API key', [
                'sale_id' => $saleId,
            ]);
            return null;
        }

        // Get the dgi_invoice record
        $dgiInvoice = DB::table('dgi_invoice')->where('sale_id', $saleId)->first();

        if ($dgiInvoice) {
            $cufe = $dgiInvoice->dgi_invoice_id;

            if (empty($cufe) && !empty($dgiInvoice->xml_response)) {
                try {
                    $xml = simplexml_load_string($dgiInvoice->xml_response);
                    if ($xml !== false) {
                        $nodes = $xml->xpath('//*[local-name()="dId"]');
                        if (!empty($nodes)) {
                            $cufe = (string) $nodes[0];
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Billing PDF XML parse warning', [
                        'sale_id' => $saleId,
                        'message' => $e->getMessage()
                    ]);
                }
            }

            if (empty($cufe)) {
                Log::error('Billing PDF Error: missing CUFE', [
                    'sale_id' => $saleId,
                    'dgi_invoice_id' => $dgiInvoice->dgi_invoice_id,
                ]);
                return false;
            }

            $jwtSecret = $this->jwtSecret;
            if (empty($jwtSecret)) {
                Log::error('Billing PDF Error: missing EMAGIC_JWT_SECRET', [
                    'sale_id' => $saleId,
                ]);
                return false;
            }

            $repoEnvironment = $this->getRepoEnvironment();

            // Función para codificar en base64url según estándar JWT
            $base64url_encode = function ($data) {
                return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
            };
            
            // Genera los componentes del JWT
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            $payload = json_encode(['cufe' => $cufe]);
            
            // Codifica header y payload en base64url
            $encoded_header = $base64url_encode($header);
            $encoded_payload = $base64url_encode($payload);
            
            // Crea la firma usando el método correcto
            $signature = hash_hmac('sha256', $encoded_header . '.' . $encoded_payload, $jwtSecret, true);
            $encoded_signature = $base64url_encode($signature);
            
            // Construye el JWT completo
            $jwt = $encoded_header . '.' . $encoded_payload . '.' . $encoded_signature;
            
            Log::debug('Billing PDF', [
                'sale_id' => $saleId,
                'jwt' => $jwt,
                'repo_environment' => $repoEnvironment,
            ]);
            
            // Crea la URL con el JWT
            $url = $this->apiUrl . '/facturador-repositorio/' . $repoEnvironment . '/v2/comprobante/' . $jwt . '/file-type/pdf?codigoPlantilla=005';
            
            // Realiza la solicitud con el encabezado requerido
            try {
                $response = Http::withHeaders([
                    'Ocp-Apim-Subscription-Key' => $apiKey
                ])->connectTimeout($this->httpConnectTimeout)
                  ->timeout($this->httpTimeout)
                  ->get($url);
            } catch (\Exception $e) {
                Log::error('Billing PDF HTTP timeout/connection error', [
                    'sale_id' => $saleId,
                    'url' => $url,
                    'message' => $e->getMessage(),
                    'connect_timeout' => $this->httpConnectTimeout,
                    'timeout' => $this->httpTimeout,
                ]);
                return false;
            }
            
            if ($response->successful()) {
                return $response->body();
            } else {
                Log::error('Billing PDF Error', [
                    'sale_id' => $saleId,
                    'url' => $url,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return false;
            }
        }

        Log::warning('Billing PDF not found: missing dgi_invoice record', [
            'sale_id' => $saleId,
        ]);
        
        return null;
    }
}