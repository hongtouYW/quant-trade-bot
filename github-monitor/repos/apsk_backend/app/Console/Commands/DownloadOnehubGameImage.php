<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use App\Models\Game;
use App\Models\Provider;
use Illuminate\Support\Facades\Storage;

class DownloadOnehubGameImage extends Command
{
    protected $signature = 'app:onehub-download-game-image';
    protected $description = 'Fetch game lists for all providers and save each into a separate file';

    public function handle()
    {
        $startTime = microtime(true); // ⏱ Start timing

        $accessToken  = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZG1pbl9hY2Nlc3Nfa2V5X2FkbWluX2lkIjoyOTAyNSwiYWRtaW5fYWNjZXNzX2tleV9pZCI6NTMzLCJpYXQiOjE3NTg2OTExNDF9.RMeHphhqvPQ9N8fm3JJBuSIzbQupEViBt2tmztqKxjM';
        $accessSecret = 'UK342TOSPLOBSVLTV3EYSC6Z';

        $url          = 'https://agent-api.netplay.vip/api/v1/agent/player/getGameList';

        $providers = [
            1  => 'JILI',
//            2  => 'PRAGMATI2C_SLOT',
//            3  => 'PG_SOFT',
//            4  => 'LUCKY365',
//            5  => 'MEGA888_H5',
//            6  => '918KISS_H5',
//            9  => 'BIG_GAMING',
//            10 => '918KISS',
//            11 => 'MEGA888',
//            12 => 'PUSSY888',
//            13 => 'SABONG388',
//            16 => 'GW_99',
//            17 => 'ACE333',
//            19 => 'JOKER',
//            20 => 'MEGA_H5',
//            21 => 'LION_KING',
//            22 => 'MONKEY_KING',
//            24 => 'W_CASINO',
//            25 => 'LIVE22',
//            26 => 'SCR2',
//            27 => 'M9_BET',
//            29 => 'BTI_SPORT',
//            30 => 'ADVANT_PLAY',
//            31 => 'SEXY_GAMING',
//            32 => 'SUNCITY',
//            33 => 'PLAYACE',
//            35 => 'DUOFU',
//            36 => 'PRAGMATIC_LIVE',
//            37 => 'UUSLOTS',
//            38 => 'VPOWER',
//            39 => 'AMB_SLOT',
//            42 => 'CLOTPLAY',
//            43 => 'PEGASUS',
//            45 => 'XE88',
//            47 => 'ALLBET',
//            48 => 'HABANERO',
//            49 => 'NEXTSPIN',
//            50 => 'SPADE_GAMING',
//            52 => 'EVOH5X100',
//            53 => 'EVOH5FREE',
//            55 => 'EVO888_APP',
//            56 => 'EVO888_H5',
//            57 => 'CT855',
//            58 => 'EVOLUTION',
//            59 => 'SCR888'
        ];

        foreach ($providers as $providerId => $providerName) {
            $timestamp = (string) round(microtime(true) * 1000);

            $body = [
                'timestamp'   => $timestamp,
                'provider_id' => $providerId,
                'lang'        => 'zh-CN',
            ];

            $jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $signature = hash_hmac('sha256', $jsonBody, $accessSecret);

            $this->info("Fetching provider: {$providerName} ({$providerId})");

            $result = [
                'request'  => $body,
                'response' => null,
                'status'   => null,
                'error'    => null,
            ];

            try {
                $response = Http::withHeaders([
                    'User-Agent'    => 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Mobile Safari/537.36',
                    'Authorization' => 'Bearer ' . $accessToken,
                    'signature'     => $signature,
                    'Content-Type'  => 'application/json',
                ])->withoutVerifying()
                    ->withBody($jsonBody, 'application/json')
                    ->post($url);

                $result['status'] = $response->status();

                if ($response->successful()) {
                    $data = $response->json();
                    $result['response'] = $data;

                    if (!empty($data['data']) && is_array($data['data'])) {
                        foreach ($data['data'] as $game) {

                            if (empty($game['game_thumbnail']) || empty($game['game_name'])) {
                                continue;
                            }
                            $dbProviderId = Provider::where('providertarget_id', $providerId)->value('provider_id');;
                            $imagePath = $this->downloadGameImage(
                                $game['game_thumbnail'],
                                $providerName,
                                'en',
                                $game['game_name']
                            );
                            $this->line("gametarget: {$game['game_id']}");
                            $this->line("imagePath: $imagePath");
                            if ($imagePath) {
                                Game::where('gametarget_id', $game['game_id'])
                                    ->where('provider_id', 2)
                                    ->update(['icon' => $imagePath]);
                                $this->line("🖼 Updated icon: {$game['game_name']}");
                            } else {
                                $this->warn("⚠️ Failed image: {$game['game_name']}");
                            }

                            if ($imagePath) {
                                $this->line("🖼 Downloaded: {$imagePath}");
                            } else {
                                $this->warn("⚠️ Failed image: {$game['game_name']}");
                            }
                        }
                    }

                    $this->info("✅ Saved {$providerName} + images");
                } else {
                    $result['error'] = 'Failed (HTTP ' . $response->status() . ')';
                    $this->warn("⚠️ {$providerName} failed with HTTP " . $response->status());
                }

            } catch (\Exception $e) {
                $result['error'] = $e->getMessage();
                $this->error("❌ Error fetching {$providerName}: " . $e->getMessage());
            }

            usleep(300000); // delay 0.3s between requests
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        $this->info("⏳ Total runtime: {$duration} seconds");
    }

    private function downloadGameImage(
        string $imageUrl,
        string $providerName,
        string $lang,
        string $gameName
    ): ?string {
        try {
            if (empty($imageUrl)) {
                return null;
            }

            $response = Http::timeout(10)->get($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            // slugify game name
            $slug = Str::slug($gameName, '_');

            // keep original extension
            $extension = pathinfo(
                parse_url($imageUrl, PHP_URL_PATH),
                PATHINFO_EXTENSION
            ) ?: 'png';

            $dbPath = sprintf(
                'assets/img/game/onehubx/%s/%s/%s.%s',
                strtolower($providerName),
                strtolower($lang),
                $slug,
                $extension
            );

            $fullPath = public_path('storage/' . $dbPath);

            File::ensureDirectoryExists(dirname($fullPath));

            file_put_contents($fullPath, $response->body());

            return $dbPath;
        } catch (\Throwable $e) {
            return null;
        }
    }

}
