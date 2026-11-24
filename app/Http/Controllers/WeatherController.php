<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class WeatherController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lon' => ['required', 'numeric', 'between:-180,180']]);
        if (blank(config('services.openweather.key'))) {
            return response()->json(['message' => 'Weather is currently unavailable.'], 503);
        }
        $latitude = round((float) $data['lat'], 3);
        $longitude = round((float) $data['lon'], 3);
        try {
            $weather = Cache::remember('weather:'.$latitude.':'.$longitude, 600, function () use ($latitude, $longitude): array {
                $result = Http::acceptJson()->timeout(8)->get('https://api.openweathermap.org/data/2.5/weather', ['lat' => $latitude, 'lon' => $longitude, 'units' => 'metric', 'appid' => config('services.openweather.key')])->throw()->json();
                if (! is_numeric(data_get($result, 'main.temp'))) {
                    throw new UnexpectedValueException('Invalid weather response.');
                }

                return ['place' => $result['name'] ?? 'Selected location', 'temperature' => $result['main']['temp'], 'description' => data_get($result, 'weather.0.description', 'Conditions unavailable'), 'humidity' => data_get($result, 'main.humidity')];
            });

            return response()->json($weather);
        } catch (ConnectionException|RequestException|UnexpectedValueException $exception) {
            return response()->json(['message' => 'Weather is currently unavailable. Please try again later.'], 503);
        }
    }
}
