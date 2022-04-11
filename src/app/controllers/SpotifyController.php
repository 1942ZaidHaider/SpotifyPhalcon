<?php

use Phalcon\Mvc\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class SpotifyController extends Controller
{
    public function initialize()
    {
        $this->spot_id = (Users::findFirst($this->session->user['id']))->spotify_id;
        $this->access = $this->session->user['access'];
        $this->url = "https://api.spotify.com/";
    }
    public function indexAction($id = null)
    {
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5,
        ]);
        try {
            $json = $client->request(
                "GET",
                "/v1/users/$this->spot_id/playlists",
                ["query" => ["access_token" => $this->access]]
            )->getBody();
        } catch (ClientException $e) {
            $this->eventsManager->fire("spotify:tokenExpired", $this);
            $this->response->redirect("spotify/index");
        }
        $this->view->playlists = json_decode($json, 1);
        if ($id) {
            $client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5,
            ]);
            try {
                $json = $client->request(
                    "GET",
                    "/v1/playlists/$id/tracks",
                    ["query" => ["access_token" => $this->access]]
                )->getBody();
            } catch (ClientException $e) {
                $this->eventsManager->fire("spotify:tokenExpired", $this);
                $json = $client->request(
                    "GET",
                    "/v1/playlists/$id/tracks",
                    ["query" => ["access_token" => $this->access]]
                )->getBody();
            }
            $this->view->playlist_data = json_decode($json, 1);
            $this->view->playListID = $id;
        }
    }
    public function newPlaylistAction()
    {
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $args = $post;
            $client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5,
                "headers" => ["Authorization" => "Bearer $this->access"]
            ]);
            try {
                $json = $client->request(
                    "POST",
                    "/v1/users/$this->spot_id/playlists",
                    ["body" => json_encode($args)]
                )->getBody();
            } catch (ClientException $e) {
                $this->eventsManager->fire("spotify:tokenExpired", $this);
                $json = $client->request(
                    "POST",
                    "/v1/users/$this->spot_id/playlists",
                    ["body" => json_encode($args)]
                )->getBody();
            }
        }
        $this->response->redirect("spotify");
    }
    public function addTrackAction($uri)
    {
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5,
        ]);
        try {
            $json = $client->request(
                "GET",
                "/v1/users/$this->spot_id/playlists",
                ["query" => ["access_token" => $this->access]]
            )->getBody();
        } catch (ClientException $e) {
            $this->eventsManager->fire("spotify:tokenExpired", $this);
            $json = $client->request(
                "GET",
                "/v1/users/$this->spot_id/playlists",
                ["query" => ["access_token" => $this->access]]
            )->getBody();
        }
        $this->view->playlists = json_decode($json, 1);
        $this->view->uri = $uri;
        if ($this->request->isPost()) {
            $post = $this->request->getPost();
            $client = new Client([
                'base_uri' => $this->url,
                'timeout'  => 5,
                "headers" => ["Authorization" => "Bearer $this->access"]
            ]);
            $args = [
                "uris" => [$post['uris']]
            ];
            $id = $post['playlist'];
            try {
                $json = $client->request(
                    "POST",
                    "/v1/playlists/$id/tracks",
                    ["body" => json_encode($args)]
                )->getBody();
            } catch (ClientException $e) {
                $this->eventsManager->fire("spotify:tokenExpired", $this);
                $json = $client->request(
                    "POST",
                    "/v1/playlists/$id/tracks",
                    ["body" => json_encode($args)]
                )->getBody();
            }
            $this->response->redirect("/index/search");
        }
    }
    public function deletePlaylistAction($id)
    {
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5,
        ]);
        try {
            $json = $client->request(
                "DELETE",
                "/v1/playlists/$id/followers",
                ["query" => ["access_token" => $this->access]]
            );
        } catch (ClientException $e) {
            $this->eventsManager->fire("spotify:tokenExpired", $this);
            $json = $client->request(
                "DELETE",
                "/v1/playlists/$id/followers",
                ["query" => ["access_token" => $this->access]]
            );
        }
        $this->response->redirect("spotify");
    }
    public function deleteTrackAction($uri, $id)
    {
        $client = new Client([
            'base_uri' => $this->url,
            'timeout'  => 5,
        ]);
        $data = [["uri" => $uri]];
        //die(json_encode(["tracks" => $data]));
        try {
            $json = $client->request(
                "DELETE",
                "/v1/playlists/$id/tracks",
                [
                    "query" => ["access_token" => $this->access],
                    "body" => json_encode(["tracks" => $data])
                ]
            );
        } catch (ClientException $e) {
            $this->eventsManager->fire("spotify:tokenExpired", $this);
            $json = $client->request(
                "DELETE",
                "/v1/playlists/$id/tracks",
                [
                    "query" => ["access_token" => $this->access],
                    "body" => json_encode(["tracks" => $data])
                ]
            );
        }
    }
    public function playerAction()
    {
        die("wip");

    }
}
