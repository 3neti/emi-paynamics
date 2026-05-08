<?php

namespace LBHurtado\EmiPaynamicsConstellation\Support;

use Closure;
use LBHurtado\EmiPaynamicsConstellation\Actions\CashOut\CreateCashOutOtp;
use LBHurtado\EmiPaynamicsConstellation\Contracts\ConstellationOtpResolver;
use RuntimeException;

class InteractiveOtpResolver implements ConstellationOtpResolver
{
    protected ?Closure $inputCallback = null;

    public function __construct(
        protected CreateCashOutOtp $createCashOutOtp,
    ) {}

    /**
     * Set a custom callback for obtaining the OTP code.
     * Useful for testing or non-CLI contexts.
     */
    public function withInputCallback(Closure $callback): static
    {
        $this->inputCallback = $callback;

        return $this;
    }

    public function resolve(array $otpRequestPayload): string
    {
        $result = $this->createCashOutOtp->handle($otpRequestPayload);

        if (! ($result['success'] ?? false)) {
            $message = $result['data']['response_message']
                ?? $result['data']['response_advise']
                ?? $result['data']
                ?? 'Unknown OTP error';

            throw new RuntimeException("OTP request failed: {$message}");
        }

        if ($this->inputCallback) {
            return ($this->inputCallback)($otpRequestPayload, $result);
        }

        // Guard: refuse to prompt when STDIN is not interactive (e.g. --json mode, piped input)
        if (! $this->isInteractive()) {
            throw new RuntimeException(
                'Paynamics OTP requires interactive input but STDIN is not a terminal. '
                .'Remove --json or use a non-interactive OTP resolver (e.g. CONSTELLATION_OTP_RESOLVER=null).'
            );
        }

        // Default: read from STDIN for CLI/lifecycle use
        $phone = $result['data'] ?? 'wallet holder';
        fwrite(STDERR, "\n[Paynamics OTP] {$phone}\n");
        fwrite(STDERR, 'Enter OTP: ');

        $input = fgets(STDIN);

        if ($input === false) {
            throw new RuntimeException('Failed to read OTP from STDIN.');
        }

        return trim($input);
    }

    protected function isInteractive(): bool
    {
        if (! defined('STDIN')) {
            return false;
        }

        return stream_isatty(STDIN);
    }
}
