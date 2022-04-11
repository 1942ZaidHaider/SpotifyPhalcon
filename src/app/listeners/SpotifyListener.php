<?php

use Phalcon\Logger;
use Phalcon\Config;
use Phalcon\Db\AdapterInterface;
use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;


/**
 * Class QueryListener
 *
 * @property Config $config
 * @property Logger $logger
 */
class SpotifyListener extends Injectable
{
    public function tokenExpired($event)
    {
        if (!$this->session->user) {
            $this->session->destroy();
            $this->response->redirect('index/index');
        } else {
            $url = "https://accounts.spotify.com";
            $path = "/api/token";
            $user = Users::findFirst($this->session->user["id"]);
            $code = $user->refresh;
            $args = [
                'grant_type' => "refresh_token",
                "refresh_token" => $code,
            ];
            $headers = [
                "Content-Type" => "application/x-www-form-urlencoded",
                "Authorization" => "Basic " . base64_encode($this->config->spotify['client_id'] . ":" . $this->config->spotify['secret']),
            ];
            $client = new Client([
                // Base URI is used with relative requests
                'base_uri' => $url,
                // You can set any number of default request options.
                'timeout'  => 2.0,
                "headers" => $headers,
            ]);
            try {
                $response = $client->request("POST", $path, ["form_params" => $args]);
            } catch (ClientException $e) {
                $this->eventsManager->fire("spotify:tokenExpired", $this);
            }
            $f = $this->session->user;
            $f["access"] = json_decode($response->getBody(), 1)['access_token'];
            $this->session->user = $f;
            $user->token = json_decode($response->getBody(), 1)['access_token'];
            $user->save();
            //die($response->getBody());
            $this->response->redirect('index/dash');
        }
    }
    public function newToken($event)
    {
        die("bad token RIP");
    }
}
