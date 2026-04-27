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

    protected function asNumber($value, int $decimals = 2): float
    {
        return round((float) $value, $decimals);
    }

    protected function normalizeLotNumber($value, int $batchId): string
    {
        $lot = trim((string) $value);

        if ($lot === '') {
            $lot = 'LOTE-' . $batchId;
        }

        if (strlen($lot) < 5) {
            $lot = 'LOT-' . $lot;
        }

        if (strlen($lot) > 35) {
            $lot = substr($lot, 0, 35);
        }

        return $lot;
    }

    protected function normalizeDgiPhone($value): ?string
    {
        $rawPhone = trim((string) $value);
        if ($rawPhone === '') {
            return null;
        }

        if (preg_match('/^\d{3,4}-\d{4}$/', $rawPhone)) {
            return $rawPhone;
        }

        $digits = preg_replace('/\D+/', '', $rawPhone);
        if ($digits === null || $digits === '') {
            return null;
        }

        $length = strlen($digits);
        if ($length === 7) {
            return substr($digits, 0, 3) . '-' . substr($digits, 3, 4);
        }

        if ($length === 8) {
            return substr($digits, 0, 4) . '-' . substr($digits, 4, 4);
        }

        return null;
    }

    protected function isValidDgiUbiCode($value): bool
    {
        if (!is_string($value) || $value === '') {
            return false;
        }

        return (bool) preg_match('/^\d{1,2}-\d{1,2}-\d{1,2}$/', $value);
    }

    protected function addPayloadError(array &$errors, string $path, string $message): void
    {
        $errors[] = $path . ': ' . $message;
    }

    protected function resolveBillingEnvironmentValues(): array
    {
        $isLiveMode = $this->isLiveMode();

        $iambOverride = (int) $this->iambOverride;
        if (!in_array($iambOverride, [1, 2], true) && $this->iambOverride !== null && $this->iambOverride !== '') {
            Log::warning('Invalid EMAGIC_IAMB override, fallback will be used', [
                'configured_value' => $this->iambOverride,
                'fallback_value' => $isLiveMode ? 1 : 2,
            ]);
        }

        $iamb = in_array($iambOverride, [1, 2], true) ? $iambOverride : ($isLiveMode ? 1 : 2);

        $denvFeOverride = (int) $this->denvFeOverride;
        if (!in_array($denvFeOverride, [1, 2], true) && $this->denvFeOverride !== null && $this->denvFeOverride !== '') {
            Log::warning('Invalid EMAGIC_DENV_FE override, fallback will be used', [
                'configured_value' => $this->denvFeOverride,
                'fallback_value' => $iamb,
            ]);
        }

        $denvFe = in_array($denvFeOverride, [1, 2], true) ? $denvFeOverride : $iamb;

        return [
            'iamb' => $iamb,
            'denvFE' => $denvFe,
        ];
    }

    protected function normalizeBillingEnvironmentInPayload(array $payload): array
    {
        $env = $this->resolveBillingEnvironmentValues();

        if (!isset($payload['gdgen']) || !is_array($payload['gdgen'])) {
            $payload['gdgen'] = [];
        }

        $payload['gdgen']['iamb'] = $env['iamb'];
        $payload['gdgen']['denvFE'] = $env['denvFE'];

        return $payload;
    }

    protected function validatePayloadForContract(array $payload): array
    {
        $errors = [];

        $gdgen = $payload['gdgen'] ?? [];
        if (!is_array($gdgen)) {
            $this->addPayloadError($errors, 'gdgen', 'must be an object');
            return $errors;
        }

        $dnroDF = (string) ($gdgen['dnroDF'] ?? '');
        if ($dnroDF === '' || !preg_match('/^\d{10}$/', $dnroDF)) {
            $this->addPayloadError($errors, 'gdgen.dnroDF', 'must be 10 numeric digits');
        }

        $dseg = (string) ($gdgen['dseg'] ?? '');
        if ($dseg === '' || !preg_match('/^\d{9}$/', $dseg)) {
            $this->addPayloadError($errors, 'gdgen.dseg', 'must be 9 numeric digits');
        }

        $dptoFacDF = (string) ($gdgen['dptoFacDF'] ?? '');
        if ($dptoFacDF === '' || !preg_match('/^\d{3}$/', $dptoFacDF)) {
            $this->addPayloadError($errors, 'gdgen.dptoFacDF', 'must be 3 numeric digits');
        }

        $gemis = $gdgen['gemis'] ?? [];
        if (!is_array($gemis)) {
            $this->addPayloadError($errors, 'gdgen.gemis', 'must be an object');
        } else {
            $emitterPhones = $gemis['dtfnEm'] ?? [];
            if (!is_array($emitterPhones) || empty($emitterPhones)) {
                $this->addPayloadError($errors, 'gdgen.gemis.dtfnEm', 'must include at least one phone');
            } else {
                $emitterPhone = (string) ($emitterPhones[0] ?? '');
                if ($emitterPhone === '' || !preg_match('/^\d{3,4}-\d{4}$/', $emitterPhone)) {
                    $this->addPayloadError($errors, 'gdgen.gemis.dtfnEm[0]', 'must follow 999-9999 or 9999-9999');
                }
            }
        }

        $iamb = (int) ($gdgen['iamb'] ?? 0);
        if (!in_array($iamb, [1, 2], true)) {
            $this->addPayloadError($errors, 'gdgen.iamb', 'must be 1 or 2');
        }

        $denvFe = (int) ($gdgen['denvFE'] ?? 0);
        if (!in_array($denvFe, [1, 2], true)) {
            $this->addPayloadError($errors, 'gdgen.denvFE', 'must be 1 or 2');
        }

        $receiver = $gdgen['gdatRec'] ?? [];
        if (!is_array($receiver)) {
            $this->addPayloadError($errors, 'gdgen.gdatRec', 'must be an object');
        } else {
            $receiverType = (string) ($receiver['itipoRec'] ?? '');
            if (!in_array($receiverType, ['01', '02', '03', '04'], true)) {
                $this->addPayloadError($errors, 'gdgen.gdatRec.itipoRec', 'must be one of 01, 02, 03, 04');
            }

            if (in_array($receiverType, ['01', '03'], true)) {
                if (empty($receiver['dnombRec'])) {
                    $this->addPayloadError($errors, 'gdgen.gdatRec.dnombRec', 'is required for itipoRec 01/03');
                }
                if (empty($receiver['ddirecRec'])) {
                    $this->addPayloadError($errors, 'gdgen.gdatRec.ddirecRec', 'is required for itipoRec 01/03');
                }

                $grucRec = $receiver['grucRec'] ?? [];
                if (!is_array($grucRec) || empty($grucRec['druc']) || !isset($grucRec['dtipoRuc'])) {
                    $this->addPayloadError($errors, 'gdgen.gdatRec.grucRec', 'druc and dtipoRuc are required for itipoRec 01/03');
                }

                $gubiRec = $receiver['gubiRec'] ?? [];
                if (!is_array($gubiRec)) {
                    $this->addPayloadError($errors, 'gdgen.gdatRec.gubiRec', 'is required for itipoRec 01/03');
                } else {
                    if (!$this->isValidDgiUbiCode($gubiRec['dcodUbi'] ?? null)) {
                        $this->addPayloadError($errors, 'gdgen.gdatRec.gubiRec.dcodUbi', 'must follow N-N-N format (example 8-8-9)');
                    }
                    foreach (['dprov', 'ddistr', 'dcorreg'] as $ubiField) {
                        if (empty($gubiRec[$ubiField])) {
                            $this->addPayloadError($errors, 'gdgen.gdatRec.gubiRec.' . $ubiField, 'is required for itipoRec 01/03');
                        }
                    }
                }
            }

            if ($receiverType === '04') {
                $gidExt = $receiver['gidExt'] ?? [];
                if (!is_array($gidExt) || empty($gidExt['didExt'])) {
                    $this->addPayloadError($errors, 'gdgen.gdatRec.gidExt.didExt', 'is required for itipoRec 04');
                }
            }
        }

        $formaPago = $payload['gtot']['gformaPago'][0] ?? null;
        if (!is_array($formaPago)) {
            $this->addPayloadError($errors, 'gtot.gformaPago[0]', 'is required');
        } else {
            $iformaPago = (string) ($formaPago['iformaPago'] ?? '');
            if ($iformaPago === '') {
                $this->addPayloadError($errors, 'gtot.gformaPago[0].iformaPago', 'is required');
            }
            if (!isset($formaPago['dvlrCuota'])) {
                $this->addPayloadError($errors, 'gtot.gformaPago[0].dvlrCuota', 'is required');
            } elseif ((float) $formaPago['dvlrCuota'] <= 0) {
                $this->addPayloadError($errors, 'gtot.gformaPago[0].dvlrCuota', 'must be greater than 0');
            }
            if ($iformaPago === '99' && empty($formaPago['dformaPagoDesc'])) {
                $this->addPayloadError($errors, 'gtot.gformaPago[0].dformaPagoDesc', 'is required when iformaPago is 99');
            }
        }

        $gtot = $payload['gtot'] ?? null;
        if (!is_array($gtot)) {
            $this->addPayloadError($errors, 'gtot', 'must be an object');
        } else {
            $requiredNumericTotals = [
                'dvtot',
                'dtotITBMS',
                'dtotRec',
                'dtotNeto',
                'dtotGravado',
                'dvtotItems',
            ];

            foreach ($requiredNumericTotals as $field) {
                if (!array_key_exists($field, $gtot)) {
                    $this->addPayloadError($errors, 'gtot.' . $field, 'is required');
                } elseif ((float) $gtot[$field] < 0) {
                    $this->addPayloadError($errors, 'gtot.' . $field, 'must be greater than or equal to 0');
                }
            }

            if (!isset($gtot['dnroItems']) || (int) $gtot['dnroItems'] <= 0) {
                $this->addPayloadError($errors, 'gtot.dnroItems', 'must be greater than 0');
            }
        }

        $items = $payload['gitem'] ?? [];
        if (!is_array($items) || empty($items)) {
            $this->addPayloadError($errors, 'gitem', 'must contain at least one item');
        } else {
            foreach ($items as $index => $item) {
                $itemPath = 'gitem[' . $index . ']';
                if (!is_array($item)) {
                    $this->addPayloadError($errors, $itemPath, 'must be an object');
                    continue;
                }

                if (empty($item['ddescProd'])) {
                    $this->addPayloadError($errors, $itemPath . '.ddescProd', 'is required');
                }
                if (empty($item['dsecItem'])) {
                    $this->addPayloadError($errors, $itemPath . '.dsecItem', 'is required');
                }
                if (!isset($item['dcantCodInt']) || (float) $item['dcantCodInt'] <= 0) {
                    $this->addPayloadError($errors, $itemPath . '.dcantCodInt', 'must be greater than 0');
                }
                if (!isset($item['gprecios']) || !is_array($item['gprecios'])) {
                    $this->addPayloadError($errors, $itemPath . '.gprecios', 'is required');
                } else {
                    foreach (['dprItem', 'dprUnit', 'dvalTotItem'] as $priceField) {
                        if (!array_key_exists($priceField, $item['gprecios'])) {
                            $this->addPayloadError($errors, $itemPath . '.gprecios.' . $priceField, 'is required');
                        } elseif ((float) $item['gprecios'][$priceField] <= 0) {
                            $this->addPayloadError($errors, $itemPath . '.gprecios.' . $priceField, 'must be greater than 0');
                        }
                    }
                }

                if (isset($item['gmedicina'])) {
                    if (!is_array($item['gmedicina'])) {
                        $this->addPayloadError($errors, $itemPath . '.gmedicina', 'must be an object');
                    } else {
                        $lot = (string) ($item['gmedicina']['dnroLote'] ?? '');
                        if ($lot === '' || strlen($lot) < 5 || strlen($lot) > 35) {
                            $this->addPayloadError($errors, $itemPath . '.gmedicina.dnroLote', 'must be 5 to 35 characters');
                        }
                        if (!isset($item['gmedicina']['dctLote']) || (float) $item['gmedicina']['dctLote'] <= 0) {
                            $this->addPayloadError($errors, $itemPath . '.gmedicina.dctLote', 'must be greater than 0');
                        }
                    }
                }
            }
        }

        return $errors;
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
            $jsonData = $this->normalizeBillingEnvironmentInPayload($jsonData);
            $apiKey = $this->resolveApiKeyForSale($sale);

            $payloadErrors = $this->validatePayloadForContract($jsonData);
            if (!empty($payloadErrors)) {
                $message = 'Invalid billing payload: ' . implode(' | ', $payloadErrors);

                Log::error('Billing payload validation failed', [
                    'sale_id' => $sale->id,
                    'errors' => $payloadErrors,
                    'request_data' => $jsonData,
                ]);

                $sale->update([
                    'meta' => array_merge($sale->meta ?? [], [
                        'billing_error' => $message,
                        'billing_status' => 'validation_error',
                        'billing_date' => now()->toDateTimeString(),
                    ]),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid billing payload',
                    'error' => $message,
                ];
            }

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
                    $compatPayload = $this->normalizeBillingEnvironmentInPayload($compatPayload);

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
        $env = $this->resolveBillingEnvironmentValues();
        $iamb = $env['iamb'];
        $denvFe = $env['denvFE'];

        // Get the business and its invoice data
        $business = Business::with('invoice_data')->findOrFail($sale->business_id);
        $invoiceData = $business->invoice_data;
        $emitterPhone = $this->normalizeDgiPhone($invoiceData->dtfnEm ?? null);
        
        // Get the customer/party data
        $party = $sale->party_id ? Party::findOrFail($sale->party_id) : $party;
        $partyInvoiceData = $party ? $sale->party_id ? $party->invoice_data : $party["invoice_data"] : null;
        // Get the sale details with products
        $saleDetails = SaleDetails::with(['product', 'batchSaleDetails.batch'])->where('sale_id', $sale->id)->get();
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
                    'dtfnEm' => $emitterPhone ? [$emitterPhone] : null,
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
                'dseg' => str_pad((string) $sale->id, 9, '0', STR_PAD_LEFT),
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
                'dnroDF' => str_pad((int)$invoiceNumber, 10, '0', STR_PAD_LEFT),
                'ientCAFE' => 1,
                'iamb' => $iamb,
                'dfechaEm' => $emissionDate,
                'idoc' => $documentType
            ],
            'dverForm' => '1.00',
            'gtot' => [
                'gformaPago' => [
                    [
                        'dvlrCuota' => $this->asNumber($sale->totalAmount, 2),
                        'iformaPago' => $paymentMethod
                    ]
                ],
                'dvtot' => $this->asNumber($sale->totalAmount, 2),
                'dtotITBMS' => $this->asNumber($sale->vat_amount ?? 0, 2),
                'dtotRec' => $this->asNumber($sale->totalAmount, 2),
                'dtotNeto' => $this->asNumber($sale->totalAmount - ($sale->vat_amount ?? 0), 2),
                'dtotGravado' => $this->asNumber($sale->vat_amount ?? 0, 2),
                'dnroItems' => count($saleDetails),
                'ipzPag' => 1,
                'dvtotItems' => $this->asNumber($sale->totalAmount, 2)
            ]
        ];

        if ((float) ($sale->discountAmount ?? 0) > 0) {
            $formattedData['gtot']['dtotDesc'] = $this->asNumber($sale->discountAmount, 2);
        }

        if ($paymentMethod === '99') {
            $formattedData['gtot']['gformaPago'][0]['dformaPagoDesc'] = 'OTROS';
        }

        if ((float) ($sale->discountAmount ?? 0) > 0) {
            $formattedData['gtot']['gdescBonif'] = [
                [
                    'dDescProd' => 'DESCUENTO GENERAL',
                    'dValDesc' => $this->asNumber($sale->discountAmount, 2),
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
        $detailSequence = 1;
        foreach ($saleDetails as $detail) {
            $product = $detail->product;
            $quantity = max((float) $detail->quantities, 1.0);
            $lineSubtotal = (float) ($detail->subtotal ?? ((float) $detail->price * $quantity));
            $vatAmount = (float) ($detail->tax_amount ?? 0);
            $lineTotal = (float) ($detail->total ?? ($lineSubtotal + $vatAmount));
            $unitNetPrice = $lineSubtotal / $quantity;

            $isMedicineByBatch = (int) ($product->is_medicine ?? 0) === 1
                && (int) ($product->track_by_batches ?? 0) === 1
                && $detail->batchSaleDetails
                && $detail->batchSaleDetails->isNotEmpty();

            if ($isMedicineByBatch) {
                $allocatedQty = max((float) $detail->batchSaleDetails->sum('quantity'), 1.0);
                $remainingSubtotal = $lineSubtotal;
                $remainingVat = $vatAmount;
                $remainingTotal = $lineTotal;
                $batchCount = $detail->batchSaleDetails->count();

                foreach ($detail->batchSaleDetails as $batchIdx => $batchSaleDetail) {
                    $batchQty = max((float) $batchSaleDetail->quantity, 0.0);
                    if ($batchQty <= 0) {
                        continue;
                    }

                    if ($batchIdx === $batchCount - 1) {
                        $batchSubtotal = $remainingSubtotal;
                        $batchVat = $remainingVat;
                        $batchTotal = $remainingTotal;
                    } else {
                        $ratio = $batchQty / $allocatedQty;
                        $batchSubtotal = round($lineSubtotal * $ratio, 6);
                        $batchVat = round($vatAmount * $ratio, 6);
                        $batchTotal = round($lineTotal * $ratio, 2);
                        $remainingSubtotal -= $batchSubtotal;
                        $remainingVat -= $batchVat;
                        $remainingTotal -= $batchTotal;
                    }

                    $lotNumber = $this->normalizeLotNumber(
                        $batchSaleDetail->batch->batch_number ?? null,
                        (int) $batchSaleDetail->batch_id
                    );

                    $formattedData['gitem'][] = [
                        'gitbmsitem' => [
                            'dtasaITBMS' => '01', // Standard rate
                            'dvalITBMS' => $this->asNumber($batchVat, 6)
                        ],
                        'dcodProd' => $product->productCode ?? ('PROD-' . $product->id),
                        'cunidad' => 'UND',
                        'ddescProd' => $product->productName,
                        'dcantCodInt' => $this->asNumber($batchQty, 2),
                        'gprecios' => [
                            'dprItem' => $this->asNumber($batchSubtotal, 6),
                            'dprUnit' => $this->asNumber($unitNetPrice, 6),
                            'dvalTotItem' => $this->asNumber($batchTotal, 2)
                        ],
                        'gmedicina' => [
                            'dnroLote' => $lotNumber,
                            'dctLote' => $this->asNumber($batchQty, 2),
                        ],
                        'dsecItem' => $detailSequence,
                    ];

                    $detailSequence++;
                }

                continue;
            }
            
            $formattedData['gitem'][] = [
                'gitbmsitem' => [
                    'dtasaITBMS' => '01', // Standard rate
                    'dvalITBMS' => $this->asNumber($vatAmount, 6)
                ],
                'dcodProd' => $product->productCode ?? ('PROD-' . $product->id),
                'cunidad' => 'UND',
                'ddescProd' => $product->productName,
                'dcantCodInt' => $this->asNumber($quantity, 2),
                'gprecios' => [
                    'dprItem' => $this->asNumber($lineSubtotal, 6),
                    'dprUnit' => $this->asNumber($unitNetPrice, 6),
                    'dvalTotItem' => $this->asNumber($lineTotal, 2)
                ],
                'dsecItem' => $detailSequence
            ];

            $detailSequence++;
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