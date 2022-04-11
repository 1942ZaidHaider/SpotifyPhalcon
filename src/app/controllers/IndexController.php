<?php

use Phalcon\Mvc\Controller;
use GuzzleHttp\Client;

class IndexController extends Controller
{
    public function indexAction()
    {
        if (isset($this->session->user)) {
            $this->response->redirect("index/search");
        }
    }
    public function authAction()
    {
        $user = null;
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $user = Users::findFirst([
                "conditions" => "email = ?1",
                "bind" => [1 => $post['email']]
            ]);
        }
        if (isset($this->session->user)) {
            $this->response->redirect("index/search");
        } elseif ($user) {
            $session = ["id" => $user->id, "access" => $user->token];
            $this->session->user = $session;
            $this->response->redirect("index/search");
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
            file_put_contents($this->config->spotify['cache'], $resJson);
            $client = new Client([
                'base_uri' => "https://api.spotify.com/",
                'timeout'  => 2.0,
            ]);
            $response = $client->request("GET", "/v1/me", ["query" => ["access_token" => $token]]);
            $spot = json_decode($response->getBody(), 1)["id"];
            $user = new Users();
            $user->token = $token;
            $user->refresh = $refresh;
            $user->email = base64_decode($this->cookies->get("current_email"));
            $user->spotify_id = $spot;
            $user->save();
            $session = ["id" => $user->id, "access" => $token];
            $this->session->user = $session;
            $this->response->redirect("index/search");
        } else {
            die("error");
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
                echo $response->getBody();
                $data = json_decode($response->getBody(), 1);
            } catch (Exception $e) {
                $this->response->redirect('index/refresh');
            }
        }
        $this->view->data = $data ?? [];
    }
    public function refreshAction()
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
            $response = $client->request("POST", $path, ["form_params" => $args]);
            $f=$this->session->user;
            $f["access"] = json_decode($response->getBody(), 1)['access_token'];
            $this->session->user=$f;
            $user->token = json_decode($response->getBody(), 1)['access_token'];
            $user->save();
            //die($response->getBody());
            $this->response->redirect('index/search');
        }
    }
}
