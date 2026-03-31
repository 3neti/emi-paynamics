<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddCustomerWallet;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class CreateCustomerCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:create-customer {--fake}';

    protected $description = 'Create a customer wallet in Constellation';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $data = [
            'first_name' => text('First name'),
            'middle_name' => text('Middle name', default: ''),
            'last_name' => text('Last name'),
            'email' => text('Email'),
            'mobile_no' => text('Mobile (e.g. 639171234567)'),
            'address' => text('Address'),
            'zip' => text('ZIP code'),
            'city' => text('City'),
            'state' => text('State/Province'),
            'country' => text('Country code', default: 'PH'),
            'username' => text('Portal username (optional)', default: ''),
            'password' => text('Portal password (optional)', default: ''),
            'birthdate' => text('Birthdate (yyyy-MM-dd)', default: ''),
            'nationality' => text('Nationality', default: 'Filipino'),
            'source_of_funds' => text('Source of funds', default: ''),
            'profile_type' => text('Profile type', default: 'DEFAULT_CONSUMER'),
            'external_uid' => text('External UID', default: (string) Str::uuid()),
            'notification_url' => text('Notification URL', default: config('constellation.notification_url', '')),
            'success_url' => text('KYC Success URL', default: ''),
            'failed_url' => text('KYC Failed URL', default: ''),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = collect($data)->except(['device_information', 'network_information'])->toArray();
        $this->logBefore($context);

        try {
            $result = AddCustomerWallet::run($data);

            if ($result['success'] ?? false) {
                $this->components->success('Customer wallet created!');
                $this->components->twoColumnDetail('Wallet ID', $result['data']['wallet_id'] ?? '');
                $this->components->twoColumnDetail('Account ID', $result['data']['account_id'] ?? '');
                $this->components->twoColumnDetail('Capture Link', $result['data']['capture_link'] ?? '');
            } else {
                $this->components->error($result['data']['response_message'] ?? 'Failed');
            }

            $this->logAfter($context, $result);

            return ($result['success'] ?? false) ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $e) {
            $this->logError($context, $e);
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
