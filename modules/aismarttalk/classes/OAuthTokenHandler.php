<?php

namespace PrestaShop\AiSmartTalk;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use Configuration;
use PrestaShop\PrestaShop\Adapter\Module\Module;

class OAuthTokenHandler extends Module
{
    public static function setOAuthTokenCookie($user)
    {
        $userEmail = $user->email;

        // Request the OAuth token from your backend
        $response = self::requestOAuthToken($user);

        if ($response === false) {
            error_log('Error retrieving OAuth token.');
        } else {
            $responseData = json_decode($response, true);
            if (isset($responseData['token'])) {
                // Set token in cookie
                $loginCookieLifetime = time() + (int) Configuration::get('PS_COOKIE_LIFETIME_BO') * 3600;
                setcookie('ai_smarttalk_oauth_token', $responseData['token'], $loginCookieLifetime, '/', null, false, true);
                $_COOKIE['ai_smarttalk_oauth_token'] = $responseData['token']; // Update $_COOKIE superglobal
            } else {
                error_log('No token found in response.');
            }
        }
    }

    public static function unsetOAuthTokenCookie()
    {
        setcookie('ai_smarttalk_oauth_token', '', time() - 3600, '/', null, false, true);
        unset($_COOKIE['ai_smarttalk_oauth_token']);
    }

    private static function requestOAuthToken($user)
    {
        $url = Configuration::get('AI_SMART_TALK_URL') . '/api/oauth/integration';
        $data = [
            'chatModelId' => Configuration::get('CHAT_MODEL_ID'),
            'token' => Configuration::get('CHAT_MODEL_TOKEN'),
            'source' => 'PRESTASHOP',
            'userId' => $user->id,
            'email' => $user->email,
        ];

        $options = [
            'http' => [
                'header'  => "Content-Type: application/json",
                'method'  => 'POST',
                'content' => json_encode($data),
            ],
        ];

        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);

        return $result;
    }
}