<?php

namespace App\Services\Rhid;

use App\Exceptions\RhidApiException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;

class RhidDeviceService
{
    public function __construct(
        private RhidClient $client,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function list(Company $company, ?User $user, array $query = []): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/device.svc/a',
            ['query' => $query, 'auditAction' => 'rhid.devices.list'],
        );

        return $this->decodeJson($r, 'devices.list');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function create(Company $company, ?User $user, array $body): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'POST',
            'customerdb/device.svc/a',
            [
                'body' => $body,
                'auditAction' => 'rhid.devices.create',
            ],
        );

        return $this->decodeJson($r, 'devices.create');
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function update(Company $company, ?User $user, array $body): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'PUT',
            'customerdb/device.svc/a',
            [
                'body' => $body,
                'auditAction' => 'rhid.devices.update',
            ],
        );

        return $this->decodeJson($r, 'devices.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(Company $company, ?User $user, int $id): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'DELETE',
            'customerdb/device.svc/a/'.$id,
            ['auditAction' => 'rhid.devices.delete'],
        );

        return $this->decodeJson($r, 'devices.delete');
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Company $company, ?User $user, int $id): array
    {
        $r = $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/device.svc/a/'.$id,
            ['auditAction' => 'rhid.devices.show'],
        );

        return $this->decodeJson($r, 'devices.show');
    }

    public function enableIdCloud(Company $company, ?User $user, int $deviceId): Response
    {
        return $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/device.svc/addDeviceiDCloud',
            [
                'query' => ['id' => $deviceId],
                'expect_json' => false,
                'accept' => '*/*',
                'auditAction' => 'rhid.devices.idcloud.enable',
            ],
        );
    }

    public function forceResyncAll(Company $company, ?User $user): Response
    {
        return $this->client->request(
            $company,
            $user,
            'GET',
            'customerdb/person.svc/force_ressync_all',
            [
                'expect_json' => false,
                'accept' => '*/*',
                'auditAction' => 'rhid.person.force_ressync_all',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(Response $response, string $context): array
    {
        if ($response->failed()) {
            throw RhidApiException::fromResponse($response, $context);
        }

        $json = $response->json();
        if (! is_array($json)) {
            throw new RhidApiException('Resposta RHID inválida (não JSON).', $response->status());
        }

        return $json;
    }
}
