<?php

declare(strict_types=1);


namespace Musement\SDK\WeatherApi;

use Musement\SDK\WeatherApi\Exception\ArgumentException;
use Musement\SDK\WeatherApi\Exception\NotFoundException;
use Musement\SDK\WeatherApi\Exception\ResponseException;
use Musement\SDK\WeatherApi\Model\Forecast;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeatherApi implements WeatherApiSDK
{
    private string $apiKey;

    private HttpClientInterface $httpClient;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->httpClient = HttpClient::createForBaseUri('http://api.weatherapi.com');
    }

    public function forecastForCoords(float $latitude, float $longitude, int $days) : Forecast
    {
        try {
            if ($days < 1) {
                throw new ArgumentException(
                    '[days] should be greater than 0'
                );
            }

            $response = $this->httpClient->request(
                $method = 'GET',
                \sprintf(
                    $url = '/v1/forecast.json?key=%s&q=%s,%s&days=%s',
                    $this->apiKey,
                    $latitude,
                    $longitude,
                    $days
                ),
                [
                    'headers' => ['Accept' => 'application/json'],
                ]
            );

            if ($response->getStatusCode() === 404) {
                throw new NotFoundException(
                    $message = 'Resource not found',
                    $response->getStatusCode()
                );
            } elseif ($response->getStatusCode() !== 200) {
                throw new ResponseException(
                    $message = 'Bad response code',
                    $response->getStatusCode()
                );
            }

            $data = \json_decode($response->getContent(), true) ?? [];

            return new Forecast(
                new Forecast\Location(
                    $data['location']['name'],
                    $data['location']['lat'],
                    $data['location']['lon'],
                ),
                new Forecast\ForecastDays(...\array_map(
                    function (array $forecastDay) {
                        return new Forecast\ForecastDays\ForecastDay(
                            $forecastDay['date'],
                            new Forecast\ForecastDays\ForecastDay\Condition(
                                $forecastDay['day']['condition']['text']
                            )
                        );
                    },
                    $data['forecast']['forecastday']
                ))
            );
        } catch (TransportExceptionInterface $exception) {
            throw new ResponseException(
                $message = 'Transport Exception',
                $exception->getCode(),
                $exception
            );
        }
    }
}
