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

class BillingService
{
    protected $apiUrl;

    public function __construct()
    {   
        // The API URL should be set in the .env file
        $this->apiUrl = env('EMAGIC_API_URL', 'https://api.facturacion.example.com');
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
            $jsonData = $this->formatSaleDataForBilling($sale, $party);
            
            // Send data to external billing API with EMAGIC_API_KEY header
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => env('EMAGIC_API_KEY')
            ])->post($this->apiUrl."/facturar/v1.1/autorizar", $jsonData);

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
            'partyInvoiceData' => $partyInvoiceData
        ]);
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
                    'dnombEm' => env("EMAGIC_MODE", "test") == "live" ? ($invoiceData->dnombEm ?? $business->companyName) : "FE generada en ambiente de pruebas - sin valor comercial ni fiscal",
                    'ddirecEm' => $invoiceData->ddirecEm ?? $business->address,
                    'grucEmi' => [
                        'ddv' => env("EMAGIC_MODE", "test") ? "37" :$invoiceData->ddv,
                        'druc' => env("EMAGIC_MODE", "test") ? "155705519-2-2021" :$invoiceData->druc,
                        'dtipoRuc' => env("EMAGIC_MODE", "test") ? 2 : ($invoiceData->dtipoRuc == "Natural" ? 1 : 2)
                    ]
                ],
                'dseg' => null,
                'itpEmis' => '01',
                'itipoSuc' => 1,
                'dptoFacDF' => str_pad($business->id, 3, '0', STR_PAD_LEFT),
                'iproGen' => 1,
                'denvFE' => 1,
                'iformCAFE' => 1,
                'idest' => 1,
                'gdatRec' => [
                    'grucRec' => $partyInvoiceData && (($partyInvoiceData->itipoRec ?? '') == "01" || ($partyInvoiceData->itipoRec ?? '') == "03") && isset($partyInvoiceData->druc) ? [
                        'dtipoRuc' => isset($partyInvoiceData->dtipoRuc) ? ($partyInvoiceData->dtipoRuc == "Natural" ? "1" : "2") : "1",
                        'druc' => $partyInvoiceData->druc,
                        'ddv' => $partyInvoiceData->ddv ?? null
                    ] : ($partyInvoiceData && (empty($partyInvoiceData->dtipoRuc) || ($partyInvoiceData->dtipoRuc ?? '') == "Jurídico") && isset($partyInvoiceData->druc) ? [
                        'dtipoRuc' => "2",
                        'druc' => $partyInvoiceData->druc,
                        'ddv' => $partyInvoiceData->ddv ?? null
                    ] : ($partyInvoiceData && isset($partyInvoiceData->druc) ? [
                        'dtipoRuc' => "1",
                        'druc' => $partyInvoiceData->druc
                    ] : null)),
                    'cpaisRec' => 'PA',
                    'gidExt' => null,
                    'dnombRec' => ($partyInvoiceData->itipoRec == "01" || $partyInvoiceData->itipoRec == "03" ) ? $partyInvoiceData->dnombRec : $partyInvoiceData->dnombRec ?? null,
                    'itipoRec' => $partyInvoiceData->itipoRec ?? "02",
                    'gubiRec' => isset($partyInvoiceData->dcodUbi) && ($partyInvoiceData->itipoRec == "01" || $partyInvoiceData->itipoRec == "03" ) ? [
                        'dcodUbi' => $partyInvoiceData->dcodUbi,
                        'dcorreg' =>  $partyInvoiceData->dcorreg,
                        'dprov' => $partyInvoiceData->dprov,
                        'ddistr' => $partyInvoiceData->ddistr
                    ] : null,
                    'gidExt' => $partyInvoiceData->itipoRec == "04" ? [
                        'didExt' => $partyInvoiceData->didExt,
                        'dpaisExt' => $partyInvoiceData->dpaisExt,
                    ] : null,
                    'ddirecRec' => ($partyInvoiceData->itipoRec == "01" || $partyInvoiceData->itipoRec == "03" ) ? $partyInvoiceData->ddirecRec ?? null : null,
                    'dcorElectRec' => $partyInvoiceData->dcorElectRec??null,
                ],
                'gfexp' => null,
                'itipoTranVenta' => 1,
                'itipoOp' => 1,
                'dnroDF' => str_pad((int)$invoiceNumber, 9, '0', STR_PAD_LEFT),
                'ientCAFE' => 1,
                'iamb' => env("EMAGIC_MODE", "test") == "live" ? 1 : 2,
                'dfechaEm' => date('Y-m-d\TH:i:sP', strtotime($sale->saleDate ?? now())),
                'gdfref' => null,
                'idoc' => '01'
            ],
            'dverForm' => '1.00',
            'gtot' => [
                'gformaPago' => [
                    [
                        'dformaPagoDesc' => null,
                        'dvlrCuota' => number_format($sale->totalAmount, 2),
                        'iformaPago' => $sale->paymentType == 'Cash' ? '01' : '02'
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
                'gdescBonif' => [],
                'dtotGravado' => number_format($sale->vat_amount ?? 0, 2),
                'dnroItems' => count($saleDetails),
                'ipzPag' => 1,
                'dvtotItems' => number_format($sale->totalAmount, 2)
            ]
        ];
        
        // Add items to the gitem array
        foreach ($saleDetails as $index => $detail) {
            $product = $detail->product;
            $itemPrice = $detail->price / $detail->quantities; // Unit price
            $itemTotal = $detail->price; // Total price
            $vatRate = $sale->vat_percent ?? 7; // Default VAT rate
            $vatAmount = ($itemTotal * $vatRate) / 100; // VAT amount
            $netPrice = $itemTotal - $vatAmount; // Net price
            
            $formattedData['gitem'][] = [
                'gitbmsitem' => [
                    'dtasaITBMS' => '01', // Standard rate
                    'dvalITBMS' => number_format($vatAmount, 6)
                ],
                'dcodProd' => $product->productCode ?? ('PROD-' . $product->id),
                'cunidad' => 'und',
                'ddescProd' => $product->productName,
                'dcantCodInt' => number_format($detail->quantities, 2),
                'gprecios' => [
                    'dprAcarItem' => '0.00',
                    'dprSegItem' => '0.00',
                    'dprItem' => number_format($netPrice, 6),
                    'dprUnit' => number_format($netPrice / $detail->quantities, 6),
                    'dprUnitDesc' => '0.00',
                    'dvalTotItem' => number_format($itemTotal, 2)
                ],
                'cunidadCPBS' => null,
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
        
        return $formattedData;
    }

    /**
     * Get the Billing PDF url
     * 
     * @return string
     */
    public function getBillingPdfFile($saleId)
    {
        $sale = Sale::findOrFail($saleId);
        $business = Business::findOrFail($sale->business_id);
        $invoiceData = $business->invoice_data;
        
        // Get the dgi_invoice record
        $dgiInvoice = DB::table('dgi_invoice')->where('sale_id', $saleId)->first();
        
        if ($dgiInvoice) {
            #generate a jwt with header: {"typ":"JWT","alg":"HS256"}
            $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
            #payload: { "cufe": $dgiInvoice->dgi_invoice_id}
            $payload = json_encode(['cufe' => $dgiInvoice->dgi_invoice_id]);
            # sign with secret key
            $signature = hash_hmac('sha256', $header . '.' . $payload, env('EMAGIC_JWT_SECRET'));
            Log::debug('Billing PDF', [
                'sale_id' => $saleId,
                'header' => $header,
                'payload' => $payload,
                'signature' => $signature
            ]);
            # create the jwt token enconde utf-8 format
            $jwt = $signature;
            #$jwt = base64_encode($header) . '.' . base64_encode($payload) . '.' . base64_encode($signature);
            # create the url https://emagic-products.azure-api.net/$jwt/file-type/pdf?codigoPlantilla=005
            $url = $this->apiUrl . '/facturador-repositorio/test/v2/comprobante/' . $jwt . '/file-type/pdf?codigoPlantilla=005';
            # add the header Ocp-Apim-Subscription-Key with value EMAGIC_API_KEY
            $response = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => env('EMAGIC_API_KEY')
            ])->get($url);
            # response is a pdf file
            if ($response->successful()) {
                return $response->body();
            } else {
                Log::error('Billing PDF Error', [
                    'sale_id' => $saleId,
                    'response' => $response->body(),
                    'status' => $response->status()
                ]);
                return false;
            }
        }
        
        return null;
    }
}