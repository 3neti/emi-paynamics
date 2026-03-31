<?php

namespace LBHurtado\EmiPaynamicsConstellation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use LBHurtado\EmiPaynamicsConstellation\Actions\Wallets\AddMerchantWallet;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\FakesConstellationHttp;
use LBHurtado\EmiPaynamicsConstellation\Console\Concerns\LogsConstellationActivity;

use function Laravel\Prompts\text;

class CreateMerchantCommand extends Command
{
    use FakesConstellationHttp;
    use LogsConstellationActivity;

    protected $signature = 'constellation:create-merchant {--fake}';

    protected $description = 'Create a merchant wallet in Constellation';

    public function handle(): int
    {
        $this->fakeIfRequested();

        $company = config('constellation.company');

        $data = [
            'company_name' => text('Company name', default: $company['name']),
            'tin' => text('TIN (max 15 chars)', default: $company['tin']),
            'email' => text('Email'),
            'mobile_no' => text('Mobile (e.g. 639171234567)', default: $company['mobile_no']),
            'website' => text('Website', default: $company['website']),
            'username' => text('Portal username'),
            'password' => text('Portal password (min 12, upper+lower+number+special)'),
            'account_first_name' => text('Representative first name', default: $company['account_first_name']),
            'account_middle_name' => text('Representative middle name', default: $company['account_middle_name']),
            'account_last_name' => text('Representative last name', default: $company['account_last_name']),
            'birthdate' => text('Birthdate (yyyy-MM-dd)', default: $company['birthdate']),
            'nationality' => text('Nationality', default: $company['nationality']),
            'source_of_funds' => text('Source of funds', default: $company['source_of_funds']),
            'business_address' => text('Business address', default: $company['business_address']),
            'business_zip' => text('Business ZIP', default: $company['business_zip']),
            'business_city' => text('Business city', default: $company['business_city']),
            'business_state' => text('Business state', default: $company['business_state']),
            'business_country' => text('Business country', default: $company['business_country']),
            'profile_type' => text('Profile type', default: 'DEFAULT_MERCHANT'),
            'external_uid' => text('External UID', default: (string) Str::uuid()),
            'notification_url' => text('Notification URL', default: config('constellation.notification_url', '')),
            'success_url' => text('KYC Success URL', default: $company['success_url']),
            'failed_url' => text('KYC Failed URL', default: $company['failed_url']),
            'device_information' => ['device_id' => 'console', 'os_version' => PHP_OS],
            'network_information' => ['ip_address' => '127.0.0.1', 'network_type' => 'console'],
        ];

        $context = collect($data)->except(['device_information', 'network_information'])->toArray();
        $this->logBefore($context);

        try {
            $result = AddMerchantWallet::run($data);

            if ($result['success'] ?? false) {
                $this->components->success('Merchant wallet created!');
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
