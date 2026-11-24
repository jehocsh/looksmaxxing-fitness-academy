<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class WeatherController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lon' => ['required', 'numeric', 'between:-180,180']]);
        $apiKey = trim((string) config('services.openweather.key'));
        if ($apiKey === '') {
            return response()->json(['message' => 'Weather is currently unavailable.', 'code' => 'weather_not_configured'], 503);
        }
        $latitude = round((float) $data['lat'], 3);
        $longitude = round((float) $data['lon'], 3);
        try {
            $weather = Cache::remember('weather:'.$latitude.':'.$longitude, 600, function () use ($latitude, $longitude, $apiKey): array {
                $result = Http::acceptJson()->connectTimeout(4)->timeout(8)->get('https://api.openweathermap.org/data/2.5/weather', ['lat' => $latitude, 'lon' => $longitude, 'units' => 'metric', 'appid' => $apiKey])->throw()->json();
                if (! is_numeric(data_get($result, 'main.temp'))) {
                    throw new UnexpectedValueException('Invalid weather response.');
                }

                return ['place' => $result['name'] ?? 'Selected location', 'temperature' => (float) $result['main']['temp'], 'description' => data_get($result, 'weather.0.description', 'Conditions unavailable'), 'humidity' => data_get($result, 'main.humidity')];
            });

            return response()->json($weather);
        } catch (RequestException $exception) {
            $status = $exception->response->status();
            $code = match ($status) {
                401, 403 => 'weather_authentication_failed',
                429 => 'weather_rate_limited',
                default => 'weather_provider_error',
            };

            return $this->unavailable($code, $status);
        } catch (ConnectionException $exception) {
            return $this->unavailable('weather_connection_failed');
        } catch (UnexpectedValueException $exception) {
            return $this->unavailable('weather_invalid_response');
        }
    }

    private function unavailable(string $code, ?int $providerStatus = null): JsonResponse
    {
        Log::warning('Weather request failed.', ['code' => $code, 'provider_status' => $providerStatus]);

        return response()->json(['message' => 'Weather is currently unavailable. Please try again later.', 'code' => $code], 503);
    }
}
