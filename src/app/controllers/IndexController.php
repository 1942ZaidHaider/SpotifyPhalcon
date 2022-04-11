<?php

use Phalcon\Mvc\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class IndexController extends Controller
{
    public function initialize()
    {
        $this->spot_id = (Users::findFirst($this->session->user['id']))->spotify_id;
        $this->access = $this->session->user['access'];
        $this->url = "https://api.spotify.com/";
    }
    public function indexAction()
    {
        if (isset($this->session->user)) {
            $this->response->redirect("index/dash");
        }
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $email = $post['email'];
            $p = $post['password'];
            $user = Users::findFirst("email = '$email' and password = '$p'");
            $session = ["id" => $user->id, "access" => $user->token];
            $this->session->user = $session;
            if ($user) {
                if ($user->token) {
                    $this->response->redirect("index/dash");
                } else {
                    $this->response->redirect("index/auth");
                }
            }
        }
    }
    public function signupAction()
    {
        $this->session->destroy();
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $email = $post['email'];
            $p = $post['password'];
            $user = new Users();
            $user->assign($post);
            $user->save();
            $this->response->redirect("index");
        }
    }
    public function dashAction()
    {
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5,
        ]);
        try {
            $json = $client->request(
                "GET",
                "/v1/me",
                ["query" => ["access_token" => $this->access]]
            )->getBody();
            $this->view->data = json_decode($json, 1);

            /** Recommendations */
            
            $client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5,
            ]);
            $args = [
                "access_token" => $this->access,
                "seed_artists" => "7dGJo4pcD2V6oG8kP0tJRR",
                "seed_tracks" => "77IURH5NC56Jn09QHi76is",
                "seed_genre" => "hip hop,rap",
            ];
            $json = $client->request(
                "GET",
                "/v1/recommendations",
                ["query" => $args]
            )->getBody();
            $this->view->rec = json_decode($json, 1);
        } catch (ClientException $e) {
            $this->eventsManager->fire("spotify:tokenExpired", $this);
            $this->response->redirect("index/dash");
        }
    }
    public function authAction()
    {
        $user = null;
        if (isset($this->session->user)) {
            $user = Users::findFirst($this->session->user['id']);
        }
        if (!$user) {
            die("<h1>You be stupid</h1>");
        } else {
            $url = "https://accounts.spotify.com";
            $args = [
                "query" => [
                    "client_id" => $this->config->spotify['client_id'],
                    "response_type" => "code",
                    'redirect_uri' => "http://localhost:8080/index/success",
                    "state" => "success",
                    "show_dialog" => 'true',
                    "scope" => implode(" ", [
                        "playlist-read-collaborative",
                        "playlist-modify-public",
                        "playlist-read-private",
                        "playlist-modify-private",
                        "user-read-private",
                        "user-read-email",
                    ]),
                ]
            ];
            $prepUlr = "$url/authorize?" . http_build_query($args['query']);
            $this->cookies->set("current_email", base64_encode($post['email']), time() + 1800);
            $this->response->redirect($prepUlr);
        }
    }
    public function successAction()
    {
        $user = null;
        if (isset($this->session->user)) {
            $user = Users::findFirst($this->session->user['id']);
        }
        if (!$user) {
            die("<h1>You be stupid</h1>");
        } else {
            $url = "https://accounts.spotify.com";
            $path = "/api/token";
            if ($this->request->getQuery("code")) {
                $code = $this->request->getQuery("code");
                $args = [
                    'grant_type' => "authorization_code",
                    "code" => $code,
                    'redirect_uri' => "http://localhost:8080/index/success",
                ];
                $headers = [
                    "Content-Type" => "application/x-www-form-urlencoded",
                    "Authorization" => "Basic " . base64_encode($this->config->spotify['client_id'] . ":" . $this->config->spotify['secret']),
                ];
                $client = new Client([
                    'base_uri' => $url,
                    'timeout'  => 5,
                    "headers" => $headers,
                ]);
                $response = $client->request("POST", $path, ["form_params" => $args]);
                echo "<pre>";
                $resJson = $response->getBody();
                $json = json_decode($resJson, 1);
                $token = $json['access_token'];
                $refresh = $json['refresh_token'];
                //file_put_contents($this->config->spotify['cache'], $resJson);
                $client = new Client([
                    'base_uri' => "https://api.spotify.com/",
                    'timeout'  => 2.0,
                ]);
                $response = $client->request("GET", "/v1/me", ["query" => ["access_token" => $token]]);
                $spot = json_decode($response->getBody(), 1)["id"];
                $user->token = $token;
                $user->refresh = $refresh;
                $user->spotify_id = $spot;
                $user->save();
                $session = ["id" => $user->id, "access" => $token];
                $this->session->user = $session;
                $this->response->redirect("index/search");
            } else {
                die("error");
            }
        }
    }
    public function searchAction()
    {
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $type = implode(",", $post['type'] ?? ['track']);
            $url = "https://api.spotify.com/";
            $client = new Client([
                'base_uri' => $url,
                'timeout'  => 2.0,
            ]);
            $args = [
                "q" => $post["q"],
                "type" => $type,
                "access_token" => $this->session->user['access'],
                "limit" => 10
            ];
            try {
                $response = $client->request("GET", "/v1/search", ["query" => $args]);
                $data = json_decode($response->getBody(), 1);
            } catch (ClientException $e) {
                $this->eventsManager->fire("spotify:tokenExpired", $this);
                $response = $client->request("GET", "/v1/search", ["query" => $args]);
                $data = json_decode($response->getBody(), 1);
            }
        }

        $this->view->data = $data ?? [];
    }
}
