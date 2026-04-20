<?php

namespace Tests\Unit;

use App\Services\BillingService;
use ReflectionClass;
use Tests\TestCase;

class BillingPayloadValidationTest extends TestCase
{
    public function test_receiver_type_02_payload_is_valid(): void
    {
        $payload = $this->basePayload();
        $payload['gdgen']['gdatRec'] = [
            'itipoRec' => '02',
            'cpaisRec' => 'PA',
            'dnombRec' => 'Consumidor Final QA',
        ];

        $errors = $this->validatePayload($payload);

        $this->assertSame([], $errors);
    }

    public function test_receiver_type_01_requires_ruc_and_location(): void
    {
        $payload = $this->basePayload();
        $payload['gdgen']['gdatRec'] = [
            'itipoRec' => '01',
            'cpaisRec' => 'PA',
            'dnombRec' => 'Cliente Contribuyente QA',
        ];

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gdgen.gdatRec.ddirecRec');
        $this->assertHasError($errors, 'gdgen.gdatRec.grucRec');
        $this->assertHasError($errors, 'gdgen.gdatRec.gubiRec.dcodUbi');
        $this->assertHasError($errors, 'gdgen.gdatRec.gubiRec.dprov');
        $this->assertHasError($errors, 'gdgen.gdatRec.gubiRec.ddistr');
        $this->assertHasError($errors, 'gdgen.gdatRec.gubiRec.dcorreg');
    }

    public function test_receiver_type_03_with_required_data_is_valid(): void
    {
        $payload = $this->basePayload();
        $payload['gdgen']['gdatRec'] = [
            'itipoRec' => '03',
            'cpaisRec' => 'PA',
            'dnombRec' => 'Entidad Gobierno QA',
            'ddirecRec' => 'Ciudad de Panama',
            'grucRec' => [
                'dtipoRuc' => 2,
                'druc' => '155705519-2-2021',
                'ddv' => '37',
            ],
            'gubiRec' => [
                'dcodUbi' => '8-8-9',
                'dprov' => 'PANAMA',
                'ddistr' => 'PANAMA',
                'dcorreg' => 'SAN FRANCISCO',
            ],
        ];

        $errors = $this->validatePayload($payload);

        $this->assertSame([], $errors);
    }

    public function test_receiver_type_04_requires_gidext_didext(): void
    {
        $payload = $this->basePayload();
        $payload['gdgen']['gdatRec'] = [
            'itipoRec' => '04',
            'cpaisRec' => 'PA',
            'dnombRec' => 'Cliente Extranjero QA',
            'gidExt' => [
                'dpaisExt' => 'CR',
            ],
        ];

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gdgen.gdatRec.gidExt.didExt');
    }

    public function test_receiver_type_01_rejects_invalid_dcodubi_format(): void
    {
        $payload = $this->basePayload();
        $payload['gdgen']['gdatRec'] = [
            'itipoRec' => '01',
            'cpaisRec' => 'PA',
            'dnombRec' => 'Cliente Contribuyente QA',
            'ddirecRec' => 'Ciudad de Panama',
            'grucRec' => [
                'dtipoRuc' => 2,
                'druc' => '155705519-2-2021',
                'ddv' => '37',
            ],
            'gubiRec' => [
                'dcodUbi' => '8-100-01',
                'dprov' => 'PANAMA',
                'ddistr' => 'SAN MIGUELITO',
                'dcorreg' => 'AMELIA DENIS DE ICAZA',
            ],
        ];

        $errors = $this->validatePayload($payload);

        $this->assertHasError($errors, 'gdgen.gdatRec.gubiRec.dcodUbi');
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
