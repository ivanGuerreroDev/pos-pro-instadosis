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

    private function basePayload(): array
    {
        return [
            'gdgen' => [
                'dnroDF' => '0000000001',
                'dseg' => '000000001',
                'dptoFacDF' => '001',
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
