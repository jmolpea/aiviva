<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Offline asymmetric (RSA-SHA256) license validator for mod_aiviva.
 *
 * Key format:   {base64url(json_payload)}.{base64url(rsa_sha256_signature)}
 * Payload:      {"wwwroot": "...", "expires": "YYYY-MM-DD"?, "edition": "..."}
 *
 * The full $CFG->wwwroot is bound into the signed payload, so a key issued for
 * one site never validates on another. Only the PUBLIC key ships here; the
 * private key stays with RSMAX Consulting S.L. and signs keys offline. Each
 * plugin has its own keypair.
 *
 * Evaluation period: on a site where no license key has ever been entered, the
 * plugin runs at full functionality for TRIAL_DAYS days from the moment it is
 * first used, so that the plugin can be evaluated (by a purchaser or by a
 * marketplace reviewer) without contacting anybody. After that window the
 * activity is blocked until a key is entered.
 *
 * Enforcement: when the license is missing, invalid, or expired the activity is
 * blocked (view.php shows a message, ajax.php returns an error, and the OpenAI
 * client refuses to run). A site administrator can always reach the plugin
 * settings to paste a key.
 *
 * @package    mod_aiviva
 * @copyright  2026 RSMAX Consulting S.L. <https://pluginia.es>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_aiviva\license;

/**
 * Offline RSA-SHA256 license validator for mod_aiviva.
 */
class validator {
    /** @var string Frankenstyle component — used for get_config() and get_string(). */
    const COMPONENT = 'mod_aiviva';

    /** @var int Length of the no-key evaluation period, in days. */
    const TRIAL_DAYS = 15;

    /**
     * RSA public key (base64 DER) used to verify license signatures.
     * Safe to ship; the matching private key never leaves RSMAX Consulting S.L.
     */
    const PUBLIC_KEY =
            'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAndHl02bef+rYBagCdM1hXQqgRz0e9feoW79AEOBCR+PsQwO3mg4N'
            . 'o0ySy07X9tnA76muFvlQvHnh0ZIbYgFniCKTdAEg0arMkzaVBMDPuAWaG5MKOL6GIY8Q9HyxZtvJLOhfAtQIBb++ulA8RyKN'
            . 'a9d9gxuW4o1CCTumxialvKPIlXY5eQdsu1SLFHeNJ1H4rD/9SXEm7+eAR9oBKeGXiSVrkbyQEVZvCHbcmEzG84KLAqNyNoK+'
            . 'oSA/MoaGRH81Y265ZAyChVurq5MKaEhc+q3IeV8+Ack3akTO6nkXLbvlWQvP1TSml0IIwRcAKczcGTGBPiEGbMLvz2Hv4pBQ'
            . 'sQIDAQAB';

    /** License is present and cryptographically valid. */
    const STATUS_VALID   = 'valid';

    /** Signature verification failed, or wwwroot does not match. */
    const STATUS_INVALID = 'invalid';

    /** Signature valid and wwwroot matches, but the expiry date has passed. */
    const STATUS_EXPIRED = 'expired';

    /** No license key has been entered in plugin settings. */
    const STATUS_MISSING = 'missing';

    /** No key entered, but the evaluation period is still running. */
    const STATUS_TRIAL   = 'trial';

    /**
     * Validate the currently-configured license key against this Moodle installation.
     *
     * When no key is configured at all, the evaluation period is evaluated
     * instead (see check_trial()).
     *
     * @return \stdClass {string status; string|null expires; string|null edition; int|null daysleft}
     */
    public static function check(): \stdClass {
        $key = trim((string) get_config(self::COMPONENT, 'license_key'));

        if ($key === '') {
            return self::check_trial();
        }

        return self::validate_key($key);
    }

    /**
     * Convenience boolean: true when the activity is allowed to run, i.e. the
     * license is valid OR the evaluation period has not expired.
     *
     * @return bool
     */
    public static function is_valid(): bool {
        $status = self::check()->status;
        return $status === self::STATUS_VALID || $status === self::STATUS_TRIAL;
    }

    /**
     * Return a user-facing message for blocking license states, or null when the
     * activity is allowed to run (valid license or running evaluation period).
     *
     * Shown on the blocked activity page and returned by AJAX errors.
     *
     * @return string|null
     */
    public static function get_banner(): ?string {
        global $CFG;

        $result = self::check();

        switch ($result->status) {
            case self::STATUS_MISSING:
                return get_string('license_banner_missing', self::COMPONENT);

            case self::STATUS_INVALID:
                return get_string('license_banner_invalid', self::COMPONENT, rtrim($CFG->wwwroot, '/'));

            case self::STATUS_EXPIRED:
                return get_string('license_banner_expired', self::COMPONENT, $result->expires);

            default: // STATUS_VALID or STATUS_TRIAL — nothing to block.
                return null;
        }
    }

    /**
     * Return a non-blocking notice to display above a working activity, or null
     * when there is nothing to say. Currently used for the evaluation period.
     *
     * @return string|null
     */
    public static function get_notice(): ?string {
        $result = self::check();

        if ($result->status !== self::STATUS_TRIAL) {
            return null;
        }

        return get_string('license_banner_trial', self::COMPONENT, (object) [
            'days'    => $result->daysleft,
            'expires' => $result->expires,
        ]);
    }

    /**
     * Return a short status string and CSS class for the settings page display.
     *
     * @return array{text: string, css: string}
     */
    public static function get_settings_status(): array {
        $result = self::check();

        switch ($result->status) {
            case self::STATUS_VALID:
                $text = $result->expires
                    ? get_string('license_status_valid', self::COMPONENT, $result->expires)
                    : get_string('license_status_valid_lifetime', self::COMPONENT);
                return ['text' => $text, 'css' => 'text-success fw-bold'];

            case self::STATUS_TRIAL:
                return [
                    'text' => get_string('license_status_trial', self::COMPONENT, (object) [
                        'days'    => $result->daysleft,
                        'expires' => $result->expires,
                    ]),
                    'css'  => 'text-info fw-bold',
                ];

            case self::STATUS_EXPIRED:
                return [
                    'text' => get_string('license_status_expired', self::COMPONENT, $result->expires),
                    'css'  => 'text-warning fw-bold',
                ];

            case self::STATUS_INVALID:
                return [
                    'text' => get_string('license_status_invalid', self::COMPONENT),
                    'css'  => 'text-danger fw-bold',
                ];

            default: // MISSING — no key and the evaluation period is over.
                return [
                    'text' => get_string('license_status_trial_expired', self::COMPONENT),
                    'css'  => 'text-danger fw-bold',
                ];
        }
    }

    // Internals.

    /**
     * Build a result object. Every field is always present so that callers never
     * touch an undefined property (which would emit a notice under DEVELOPER
     * debugging).
     *
     * @param  string      $status   One of the STATUS_* constants.
     * @param  string|null $expires  Expiry date as YYYY-MM-DD, or null.
     * @param  string|null $edition  Edition name from the payload, or null.
     * @param  int|null    $daysleft Whole days remaining, for the trial only.
     * @return \stdClass
     */
    private static function result(
        string $status,
        ?string $expires = null,
        ?string $edition = null,
        ?int $daysleft = null
    ): \stdClass {
        return (object) [
            'status'   => $status,
            'expires'  => $expires,
            'edition'  => $edition,
            'daysleft' => $daysleft,
        ];
    }

    /**
     * Evaluate the no-key evaluation period.
     *
     * The start timestamp is recorded in config_plugins the first time this runs
     * and is never rewritten, so clearing the (empty) license key field does not
     * restart the clock. Uninstalling the plugin does reset it, which is
     * acceptable: the evaluation period is commercial friction, not a security
     * boundary.
     *
     * @return \stdClass
     */
    private static function check_trial(): \stdClass {
        $started = (int) get_config(self::COMPONENT, 'trial_started');

        if ($started <= 0) {
            $started = time();
            set_config('trial_started', $started, self::COMPONENT);
        }

        $endsat = $started + (self::TRIAL_DAYS * DAYSECS);
        $now    = time();

        if ($now >= $endsat) {
            return self::result(self::STATUS_MISSING, date('Y-m-d', $endsat), null, 0);
        }

        $daysleft = (int) ceil(($endsat - $now) / DAYSECS);

        return self::result(self::STATUS_TRIAL, date('Y-m-d', $endsat), 'trial', $daysleft);
    }

    /**
     * Perform cryptographic validation of a non-empty license key string.
     *
     * @param  string $key  Trimmed license key.
     * @return \stdClass
     */
    private static function validate_key(string $key): \stdClass {
        $dotpos = strrpos($key, '.');
        if ($dotpos === false || $dotpos === 0 || $dotpos === strlen($key) - 1) {
            return self::result(self::STATUS_INVALID);
        }

        $payloadb64 = substr($key, 0, $dotpos);
        $sigpart    = substr($key, $dotpos + 1);

        // Step 1: RSA-SHA256 signature verification with the embedded public key.
        $signature = base64_decode(strtr($sigpart, '-_', '+/'), true);
        $publickey = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(self::PUBLIC_KEY, 64, "\n")
            . "-----END PUBLIC KEY-----\n";
        if (
            $signature === false
                || !function_exists('openssl_verify')
                || openssl_verify($payloadb64, $signature, $publickey, OPENSSL_ALGO_SHA256) !== 1
        ) {
            return self::result(self::STATUS_INVALID);
        }

        // Step 2: Decode payload.
        $payloadjson = base64_decode(strtr($payloadb64, '-_', '+/'), true);
        if ($payloadjson === false) {
            return self::result(self::STATUS_INVALID);
        }

        $payload = json_decode($payloadjson, true);
        if (!is_array($payload) || empty($payload['wwwroot'])) {
            return self::result(self::STATUS_INVALID);
        }

        // Step 3: wwwroot binding — exact match against $CFG->wwwroot.
        global $CFG;
        $siteroot = rtrim($CFG->wwwroot, '/');
        $keyroot  = rtrim($payload['wwwroot'], '/');

        if ($siteroot !== $keyroot) {
            return self::result(self::STATUS_INVALID);
        }

        // Step 4: Expiry check. Absent 'expires' means lifetime license.
        $expires = null;
        $edition = $payload['edition'] ?? null;

        if (!empty($payload['expires'])) {
            try {
                $expirydate = new \DateTime($payload['expires']);
                $expires    = $expirydate->format('Y-m-d');

                if (new \DateTime() > $expirydate) {
                    return self::result(self::STATUS_EXPIRED, $expires, $edition);
                }
            } catch (\Exception $e) {
                return self::result(self::STATUS_INVALID);
            }
        }

        return self::result(self::STATUS_VALID, $expires, $edition);
    }
}
