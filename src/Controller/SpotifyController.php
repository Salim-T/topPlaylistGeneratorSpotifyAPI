<?php

namespace App\Controller;

use Psr\Cache\CacheItemPoolInterface;
use SpotifyWebAPI\Session;
use SpotifyWebAPI\SpotifyWebAPI;
use SpotifyWebAPI\SpotifyWebAPIAuthException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SpotifyController extends AbstractController
{
    public function __construct(
        private readonly SpotifyWebAPI $api,
        private readonly Session $session,
        private readonly CacheItemPoolInterface $cache
    ){
    }

    #[Route('/', name:'app_spotify_update_playlist')]
    public function updatePlaylist(): Response
    {
        if(!$this->cache->hasItem('spotify_access_token')){
            return $this->redirectToRoute('app_spotify_login');
        }

        $this->api->setAccessToken($this->cache->getItem('spotify_access_token')->get());

        $top50 = $this->api->getMyTop('tracks', [
            'limit' => 50,
            'time_range' => 'medium_term',
        ]);

        $top50TracksIds = array_map(function($track){
            return $track->id;
        }, $top50->items);

        $playlistd = $this->getParameter('SPOTIFY_TOP_50_PLAYLIST_ID');

        $this->api->replacePlaylistTracks($playlistd, $top50TracksIds);

        return $this->render('spotify/index.html.twig', [
            'tracks' => $this->api->getPlaylistTracks($playlistd),
        ]);

        // $playlist = $this->api->createPlaylist([
        //     'name' => 'Top 50',
        //     'description' => 'My top 50 tracks',
        //     'public' => false,
        // ]);

        // $this->api->addPlaylistTracks($playlist->id, $top50->items);

        // return new Response('Playlist updated');
    }

    #[Route('/callback', name:'app_spotify_callback')]
    public function callbackFromSpotify(Request $request): Response
    {
        try {
            $this->session->requestAccessToken($request->query->get('code'));
        } catch (SpotifyWebAPIAuthException $e) {
            return new Response($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        $cacheItem = $this->cache->getItem('spotify_access_token');
        $cacheItem->set($this->session->getAccessToken());
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        return $this->redirectToRoute('app_spotify_update_playlist');

    }

    #[Route('/login', name:'app_spotify_login')]
    public function login(): Response
    {
        $options = [
            /* https://developer.spotify.com/documentation/web-api/concepts/scopes/ */
            'scope' => [
                'user-read-email',
                'user-read-private',
                'playlist-read-private',
                'playlist-modify-private',
                'playlist-modify-public',
                'user-top-read',
            ],
        ];

        return $this->redirect($this->session->getAuthorizeUrl($options));
    }


}
