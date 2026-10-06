<?php
/***************************************************************************
 * for license information see LICENSE.md
 *  Session data handling with cookies
 *  See doc/cookies.txt for more information in cookies.
 ***************************************************************************/

namespace Oc\Session;

class SessionDataCookie implements SessionDataInterface
{
    /**
     * @var bool
     */
    public $changed = false;

    /**
     * @var array
     */
    public $values = [];

    public function __construct()
    {
        global $opt;

        if (isset($_COOKIE[$opt['session']['cookiename'] . '_session'])) {
            //get the cookie_vars-array
            $decoded = base64_decode($_COOKIE[$opt['session']['cookiename'] . '_session'], true);

            if ($decoded !== false) {
                $this->values = @json_decode($decoded, true);
                if (!is_array($this->values)) {
                    $this->values = [];
                }
            } else {
                $this->values = [];
            }
        }
    }

    /**
     * @param mixed|null $value
     * @param mixed|null  $default
     */
    public function set(string $name, $value, $default = null): void
    {
        // Store cookie value in internal array. OcSmarty will call this->header()
        // to actually set the cookie.
        if (!isset($this->values[$name]) || $this->values[$name] != $value) {
            if ($value == $default) {
                if (isset($this->values[$name])) {
                    unset($this->values[$name]);
                    $this->changed = true;
                }
            } else {
                $this->values[$name] = $value;
                $this->changed = true;
            }
        }
    }

    /**
     * @param mixed|null $default
     * @return mixed|null
     */
    public function get(string $name, $default = null)
    {
        return $this->values[$name] ?? $default;
    }

    public function is_set(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function un_set(string $name): void
    {
        if (isset($this->values[$name])) {
            unset($this->values[$name]);
            $this->changed = true;
        }
    }

    public function header(): void
    {
        global $opt;

        if ($this->changed === true) {
            if (count($this->values) === 0) {
                // logout / cleared session: delete with the same path and domain it was set with
                setcookie(
                    $opt['session']['cookiename'] . '_session',
                    '',
                    time() - 3600,
                    $opt['session']['path'] ?? '/',
                    $opt['session']['domain'] ?? ''
                );

                return;
            }
            $value = base64_encode(json_encode($this->values));
            // https used for request and https is available, then set cookie https only
            $https_session = $opt['page']['https']['active']
                && $opt['page']['https']['mode'] != HTTPS_DISABLED
                && $this->is_set('sessionid') // only force https while login
                && !empty($this->get('sessionid'));

            setcookie(
                $opt['session']['cookiename'] . '_session',
                $value,
                time() + 365 * 24 * 60 * 60,
                $opt['session']['path'] ?? '/',
                $opt['session']['domain'] ?? '',
                $https_session // https only?
            );

            // if site is requested by http no session data is visible, so set cookie as flag to redirect to https
            setcookie(
                $opt['session']['cookiename'] . 'https_session',
                $https_session,
                time() + 365 * 24 * 60 * 60,
                $opt['session']['path'] ?? '/',
                $opt['session']['domain'] ?? '',
                false, // must be available for http
                true // communication only, no js
            );
        }
    }

    public function close(): void
    {
        // Called by OcSmarty before every display/redirect. It used to delete the
        // session cookie, which logged the user out on the next request whenever
        // the cookie was host-only (empty session domain). Changes are written by header().
    }
}
