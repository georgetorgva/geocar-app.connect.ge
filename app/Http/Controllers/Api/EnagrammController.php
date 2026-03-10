<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;

class EnagrammController
{
    public const API_ADDRESS = 'https://enagramm.com';
    public const CACHE_EXPIRATION = 31536000;
    public const AUDIO_DIR = 'static/enagramm/';

    public function clearCache(Request $request)
    {
        $input = $request -> only(['voice', 'text']);

        $rules = [
            'text' => 'nullable|string|max:300',
            'voice' => 'nullable|integer|in:0,1'
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return response($response, 400);
        }

        $voiceNum = $input['voice'] ?? 0;
        $text = $input['text'] ?? '';
        $filesDeleted = 0;

        if (!is_dir(self::AUDIO_DIR))
        {
            return response(['error' => 'invalid directory'], 500);
        }

        if ($text)
        {
            $hashedText = hash('sha256', $voiceNum . $text);
            $cachedFileName = self::AUDIO_DIR . $hashedText . '.wav';

            if (file_exists($cachedFileName))
            {
                unlink($cachedFileName);

                $filesDeleted++;
            }

            return response(['filesDeleted' => $filesDeleted]);
        }

        $files = scandir(self::AUDIO_DIR);

        foreach ($files as $file)
        {
            if ($file === '.' || $file === '..') continue;

            $filePath = self::AUDIO_DIR . $file;

            if (is_file($filePath))
            {
                unlink($filePath);

                $filesDeleted++;
            }
        }

        return response(['filesDeleted' => $filesDeleted]);
    }

    public function getAudio(Request $request)
    {
        $response['success'] = false;

        $input = $request->only(['language', 'text', 'voice']);

        $allowedLanguages = array_keys(config('app.locales'));

        $rules = [
            'language' => ['bail', 'required', 'string', Rule::in($allowedLanguages)],
            'text' => ['bail', 'required', 'string', 'max:500'],
            'voice' => ['bail', 'nullable', 'integer', 'in:0,1']
        ];

        $validator = Validator::make($input, $rules);

        if ($validator->fails())
        {
            $response['error'] = $validator->errors()->first();

            return response($response, 400);
        }

        $voiceNum = $input['voice'] ?? 0;

        $hashedText = hash('sha256', $voiceNum . $input['text']);
        $cachedFileName = self::AUDIO_DIR . $hashedText . '.wav';

        if (file_exists($cachedFileName))
        {
            $response['filePath'] = env('APP_URL') . $cachedFileName;
            $response['success'] = true;

            return $response;
        }

        $cachedAccessToken = Cache::store('file')->get('enagrammAccessToken');
        $cachedRefreshToken = Cache::store('file')->get('enagrammRefreshToken');

        $allowedToRegenerate = $cachedAccessToken && $cachedRefreshToken;
        $authToken = !$allowedToRegenerate ? $this -> login() : $cachedAccessToken;

        if (!$authToken)
        {
            $response['error'] = 'ACCESS_TOKEN_NOT_DEFINED';

            return response($response, 500);
        }

        $voiceResponse = $this -> getVoicePath($authToken, $input['language'], $input['text'], $voiceNum);

        if ($voiceResponse['status'] == 401)
        {
            if ($allowedToRegenerate)
            {
                $authToken = $this -> getNewAccessToken($cachedAccessToken, $cachedRefreshToken);

                if (!$authToken) $authToken = $this -> login(); // if refresh token expires
            }

            else $authToken = $this -> login();

            $voiceResponse = $this -> getVoicePath($authToken, $input['language'], $input['text'], $voiceNum);
        }

        elseif ($voiceResponse['status'] == 422)
        {
            $response['error'] = 'UNPROCESSABLE_CONTENT';

            return response($response, 422);
        }

        elseif ($voiceResponse['status'] == 429)
        {
            $response['error'] = 'TOO_MANY_REQUESTS';

            return response($response, 429);
        }

        $response['fullResponse'] = $voiceResponse;

        if (!isset($voiceResponse['parsed']['AudioFilePath']))
        {
            $response['error'] = 'UNABLE_TO_GET_AUDIO';

            return response($response, 500);
        }

        $response['filePath'] = $voiceResponse['parsed']['AudioFilePath'];
        $response['success'] = true;

        $this->cacheAudio($cachedFileName, $response['filePath']);

        return $response;
    }

    private function login()
    {
        $uri = self::API_ADDRESS . '/API/Account/Login';

        $options['body'] = [
            'Email' => env('ENAGRAMM_EMAIL'),
            'Password' => env('ENAGRAMM_PASSWORD')
        ];

        $options['isJson'] = true;

        $response = $this->request($uri, 'POST', $options);
        $decodedResponse = json_decode($response['body'], true);

        $accessToken = $decodedResponse['AccessToken'] ?? '';
        $refreshToken = $decodedResponse['RefreshToken'] ?? '';

        if ($accessToken && $refreshToken)
        {
            Cache::store('file')->put('enagrammAccessToken', $accessToken, self::CACHE_EXPIRATION);
            Cache::store('file')->put('enagrammRefreshToken', $refreshToken, self::CACHE_EXPIRATION);
        }

        return $accessToken;
    }

    private function cacheAudio($cachedFileName, $audioFilePath)
    {
        try
        {
            if (!is_writable(self::AUDIO_DIR)) return false;

            $stream = fopen($cachedFileName, 'w');

            if ($stream === false)
            {
                return false;
            }

            $fileResponse = Http::connectTimeout(5)->sink($stream)->get($audioFilePath);

            fclose($stream);

            $statusCode = $fileResponse->getStatusCode();

            if ($statusCode === 200) return true;

            if (File::exists($cachedFileName))
            {
                File::delete($cachedFileName);
            }
        }

        catch (\Exception $exception)
        {
            if (File::exists($cachedFileName)) File::delete($cachedFileName);

            return false;
        }
    }

    private function getNewAccessToken($accessToken, $refreshToken)
    {
        $uri = self::API_ADDRESS . '/API/Account/RefreshToken';

        $options['body'] = [
            'AccessToken' => $accessToken,
            'RefreshToken' => $refreshToken
        ];

        $response = $this->request($uri, 'POST', $options);
        $decodedResponse = json_decode($response['body'], true);

        $newAccessToken = $decodedResponse['AccessToken'] ?? '';
        $newRefreshToken = $decodedResponse['RefreshToken'] ?? '';

        if ($newRefreshToken && $newAccessToken)
        {
            Cache::store('file')->put('enagrammRefreshToken', $newRefreshToken, self::CACHE_EXPIRATION);
            Cache::store('file')->put('enagrammAccessToken', $newAccessToken, self::CACHE_EXPIRATION);
        }

        return $newAccessToken;
    }

    private function getVoicePath($token, $language, $text, $voice = 0)
    {
        $languagesMap = [
            'ge' => 'ka',
            'en' => 'en'
        ];

        $uri = self::API_ADDRESS . '/API/TTS/SynthesizeTextAudioPath';

        $options['body'] = [
            'Language' => $languagesMap[$language],
            'Text' => $text,
            'Voice' => $voice
        ];

        $options['headers'] = [
            'Authorization' => 'Bearer ' . $token
        ];

        $options['isJson'] = true;

        $apiResponse = $this->request($uri, 'POST', $options);

        $response['raw'] = $apiResponse['body'];
        $response['parsed'] = json_decode($response['raw'], true);
        $response['status'] = $apiResponse['status'];

        return $response;
    }

    private function request($uri, $method, $options)
    {
        $body = $options['body'] ?? null;
        $headers = $options['headers'] ?? [];
        $isJson = $options['isJson'] ?? false;
        $method = strtoupper($method);

        $client = Http::withHeaders($headers);

        if ($isJson && $body)
        {
            $client->asJson();
        }

        try
        {
            $apiResponse = $method === 'POST' ? $client->post($uri, $body ?? []) : $client->get($uri, $body ?? []);

            return [
                'body' => $apiResponse->body(),
                'status' => $apiResponse->status()
            ];
        }

        catch (\Exception $exception)
        {
            return [
                'body' => '',
                'status' => 500,
                'message' => $exception->getMessage()
            ];
        }
    }
}