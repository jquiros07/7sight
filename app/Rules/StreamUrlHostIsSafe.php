<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks RTSP stream URLs whose host resolves to a loopback, link-local
 * (which includes the 169.254.169.254 cloud metadata endpoint), or other
 * reserved/non-routable IP range - without this, a camera registration
 * could make MediaMTX/ffmpeg reach services on the host's own network that
 * were never meant to be exposed this way (SSRF).
 *
 * RFC1918 private ranges (10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16) are
 * intentionally allowed - that's where real IP cameras actually live.
 * PHP's FILTER_FLAG_NO_RES_RANGE excludes exactly the reserved ranges
 * (loopback, link-local, documentation/test ranges, multicast, etc.)
 * without excluding those private ranges - no manual CIDR list needed.
 */
class StreamUrlHostIsSafe implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $fail('The :attribute must include a valid host.');

            return;
        }

        // gethostbyname() only resolves A records and returns the hostname
        // unchanged when resolution fails - either way, the FILTER_VALIDATE_IP
        // check below rejects non-IP strings, so failed/IPv6-only lookups
        // fail closed rather than silently passing through.
        $ip = filter_var($host, FILTER_VALIDATE_IP) ?: gethostbyname($host);

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
            $fail('The :attribute must not point to a loopback, link-local, or other internal-only network address.');
        }
    }
}
