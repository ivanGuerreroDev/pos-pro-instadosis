<?php

namespace Tests\Unit;

use App\Services\BillingService;
use ReflectionClass;
use Tests\TestCase;

class BillingPayloadValidationTotalsTest extends TestCase
{
    public function test_payment_type_99_requires_description(): void
    {
        $payload = $this->basePayload();
        $payload['gtot']['gformaPago'][0]['iformaPago'] = '99';
        unset($payload['gtot']['gformaPago'][0]['dformaPagoDesc']);

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gtot.gformaPago[0].dformaPagoDesc');
    }

    public function test_payment_type_99_with_description_is_valid(): void
    {
        $payload = $this->basePayload();
        $payload['gtot']['gformaPago'][0]['iformaPago'] = '99';
        $payload['gtot']['gformaPago'][0]['dformaPagoDesc'] = 'PAGO ESPECIAL';

        $errors = $this->validatePayload($payload);

        $this->assertSame([], $errors);
    }

    public function test_payment_installment_value_must_be_greater_than_zero(): void
    {
        $payload = $this->basePayload();
        $payload['gtot']['gformaPago'][0]['dvlrCuota'] = 0;

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gtot.gformaPago[0].dvlrCuota');
    }

    public function test_item_price_fields_are_required(): void
    {
        $payload = $this->basePayload();
        unset($payload['gitem'][0]['gprecios']['dprUnit']);

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gitem[0].gprecios.dprUnit');
    }

    public function test_totals_reject_negative_values(): void
    {
        $payload = $this->basePayload();
        $payload['gtot']['dvtotItems'] = -1;

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gtot.dvtotItems');
    }

    public function test_decimal_formatter_keeps_two_decimals_for_payload_totals(): void
    {
        $service = app(BillingService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('asDecimalString');
        $method->setAccessible(true);

        $formatted = $method->invoke($service, 2, 2);

        $this->assertSame('2.00', $formatted);
    }

    public function test_decimal_field_formatter_normalizes_requested_totals_and_item_amounts(): void
    {
        $service = app(BillingService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('formatDecimalFields');
        $method->setAccessible(true);

        $formattedTotals = $method->invoke($service, [
            'dvtot' => 2.5,
            'dtotITBMS' => 0,
            'dtotRec' => 2.5,
            'dtotNeto' => 2.5,
            'dtotGravado' => 0,
            'dvtotItems' => 2.5,
        ], [
            'dvtot' => 2,
            'dtotITBMS' => 2,
            'dtotRec' => 2,
            'dtotNeto' => 2,
            'dtotGravado' => 2,
            'dvtotItems' => 2,
        ]);

        $this->assertSame('2.50', $formattedTotals['dvtot']);
        $this->assertSame('0.00', $formattedTotals['dtotITBMS']);
        $this->assertSame('2.50', $formattedTotals['dtotRec']);
        $this->assertSame('2.50', $formattedTotals['dtotNeto']);
        $this->assertSame('0.00', $formattedTotals['dtotGravado']);
        $this->assertSame('2.50', $formattedTotals['dvtotItems']);

        $formattedPrices = $method->invoke($service, [
            'dprItem' => 2.5,
            'dprUnit' => 2.5,
            'dvalTotItem' => 2.5,
            'dvalITBMS' => 0,
        ], [
            'dprItem' => 6,
            'dprUnit' => 6,
            'dvalTotItem' => 2,
            'dvalITBMS' => 6,
        ]);

        $this->assertSame('2.500000', $formattedPrices['dprItem']);
        $this->assertSame('2.500000', $formattedPrices['dprUnit']);
        $this->assertSame('2.50', $formattedPrices['dvalTotItem']);
        $this->assertSame('0.000000', $formattedPrices['dvalITBMS']);
    }

    private function basePayload(): array
    {
        return [
            'gdgen' => [
                'dnroDF' => '0000000001',
                'dseg' => '000000001',
                'dptoFacDF' => '001',
                'gemis' => [
                    'dtfnEm' => ['5555-5555'],
                ],
                'iamb' => 1,
                'denvFE' => 1,
                'gdatRec' => [
                    'itipoRec' => '02',
                    'cpaisRec' => 'PA',
                    'dnombRec' => 'Consumidor Final QA',
                ],
            ],
            'gtot' => [
                'gformaPago' => [
                    [
                        'iformaPago' => '02',
                        'dvlrCuota' => 10,
                    ],
                ],
                'dvtot' => 10,
                'dtotITBMS' => 0,
                'dtotRec' => 10,
                'dtotNeto' => 10,
                'dtotGravado' => 0,
                'dvtotItems' => 10,
                'dnroItems' => 1,
            ],
            'gitem' => [
                [
                    'dsecItem' => 1,
                    'ddescProd' => 'Producto QA',
                    'dcantCodInt' => 1,
                    'gprecios' => [
                        'dprItem' => 10,
                        'dprUnit' => 10,
                        'dvalTotItem' => 10,
                    ],
                ],
            ],
        ];
    }

    private function validatePayload(array $payload): array
    {
        $service = app(BillingService::class);
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('validatePayloadForContract');
        $method->setAccessible(true);

        return $method->invoke($service, $payload);
    }

    private function assertHasError(array $errors, string $pathPrefix): void
    {
        foreach ($errors as $error) {
            if (str_starts_with($error, $pathPrefix . ':')) {
                $this->assertTrue(true);
                return;
            }
        }

        $this->fail('Expected error path ' . $pathPrefix . ' not found. Errors: ' . implode(' | ', $errors));
    }
}
