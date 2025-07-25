<?php
namespace taoka3\Threads;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;
use Carbon\Carbon;

class Threads
{
    public $appId;
    public $apiSecret;
    public $redirectUri;
    public $userId = null;
    public $longAccessToken = null;
    public $code = null;
    public $endPointUri;
    public $version;
    public $result = null;
    public $creation_id = null;
    public $expires_in = null;
    public $limitDate = null;


    public function __construct()
    {
        $this->appId = config('threads.appid');
        $this->apiSecret = config('threads.apiSecret');
        $this->redirectUri = config('threads.redirectUri');
        $this->endPointUri = config('threads.endPointUri');
        $this->version = config('threads.version');

        return $this;
    }

    /**
     * authorize urlを叩いてアプリを認証
     */
    public function authorize($uri = 'authorize')
    {
        $url = 'https://threads.net/oauth/authorize';


        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
        ];
        $params = [
            'client_id' => $this->appId,
            'scope' => 'threads_basic,threads_content_publish',
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
        ];

        return $url .'?'. http_build_query($params);
    }

    /**
     * long_access_tokenとuser_idを読み込み
     */
    public function getlongAccessTokenAndUserId()
    {
        try {
            $result = DB::table('threads')->select('user_id', 'long_access_token')->first();
            if ($result) {
                $this->userId = (string)$result->user_id;
                $this->longAccessToken = $result->long_access_token;
            }
        } catch (\Exception $e) {
            report($e);
            abort(500, 'DB Error');
        }

        return $this;
    }

    /**
     * 認証後．コールバックされるのでcodeを取得する
     */
    public function redirectCallback()
    {
        $code = str_replace('#_', '', request()->query('code'));
        $this->code = $code;

        return $this;
    }

    /**
     * ショートアクセストークンを取得する
     */
    public function getAccessToken($uri = 'oauth/access_token')
    {
        $url = $this->endPointUri . $uri;

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
        ];
        $params = [
            'client_id' => $this->appId,
            'client_secret' => $this->apiSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->redirectUri,
            'code' => $this->code
        ];
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $this->result = json_decode($response);
        $this->userId = $this->result->user_id;

        return $this;
    }

    /**
     * ショートアクセストークンをロングアクセストークンへ変換する
     */
    public function changeLongAccessToken()
    {
        $url = "https://graph.threads.net/access_token?grant_type=th_exchange_token&client_secret={$this->apiSecret}&access_token={$this->result?->access_token}";
        $response = file_get_contents($url);
        $res = json_decode($response);
        $this->longAccessToken = $res->access_token;

        return $this;
    }

    /**
     * ロングアクセストークンをリフレッシュする 60日で切れるらしい．
     */
    public function refreshLongAccessToken()
    {
        $url = "https://graph.threads.net/refresh_access_token?grant_type=th_refresh_token&access_token={$this->longAccessToken}";
        $response = file_get_contents($url);
        $res = json_decode($response);
        $this->longAccessToken = $res->access_token;
        $this->expires_in = $res?->expires_in;
        $this->limitDate = $this->getExpiresDate();
        return $this;
    }

    /**
     * ロングアクセストークンをリフレッシュする 60日で切れるらしいのでチェック．
     */
    public function checkRefreshLongAccessToken()
    {
        try {
            $result = DB::table('threads')->select('limit_date')->where('user_id', (int)$this->userId)->first();
            $limitDate = $result ? $result->limit_date : null;
        } catch (\Exception $e) {
            report($e);
            abort(500, 'DB Error');
        }

        if ($limitDate) {
            // 現在時刻を取得
            $now = Carbon::now();

            // 指定された日時を取得
            $specifiedDateTime = Carbon::parse($limitDate);

            // 7日後の日時を計算
            $sevenDaysAfter = $now->copy()->addDays(7);

            // 指定日時が7日後より前か判定
            if ($specifiedDateTime < $sevenDaysAfter) {
                return true;
            }
            return false;
        }

        return true;
    }

    /**
     * expires_in の値(秒数）を現在日時に加算して有効期限年月日を取得する
     */
    public function getExpiresDate()
    {
        $expiryDate = Carbon::now()->addSeconds($this->expires_in);
        return $expiryDate->format('Y-m-d H:i:s');
    }

    /**
     * Threadsへ投稿するためのIDの切り出しを行う
     */
    public function post($text, $imgUrl = null, $media_type = 'TEXT', $uri = '/threads')
    {

        $url = $this->endPointUri . $this->version . $this->userId . $uri;

        $ch = curl_init($url);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
        ];
        $params = [
            'text' => $text,
            'access_token' => $this->longAccessToken,
            'media_type' => $media_type,
        ];

        if ($media_type === 'IMAGE') {
            $params = $params + ['image_url' => $imgUrl];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);
        $creation_id = json_decode($response);
        $this->creation_id = $creation_id?->id;

        return $this;
    }

    /**
     * Threadsへ投稿する
     */
    public function publishPost($uri = '/threads_publish')
    {
        if ($this->creation_id) {
            $url = $this->endPointUri . $this->version . $this->userId . $uri;

            $ch = curl_init($url);

            $headers = [
                'Content-Type: application/x-www-form-urlencoded; charset=utf-8'
            ];
            $params = [
                'creation_id' => $this->creation_id,
                'access_token' => $this->longAccessToken,
            ];

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            curl_close($ch);
            $response = json_decode($response);

            var_dump($response);
        }
        return $this;
    }

    /**
     * データを初回DBに保存する
     */
    public function save()
    {
        try {
            DB::table('threads')->insert([
                'user_id' => (int)$this->userId,
                'long_access_token' => $this->longAccessToken,
            ]);
        } catch (\Exception $e) {
            report($e);
            abort(500, 'DB Error');
        }
    }

    /**
     * データを更新する
     */
    public function setUpdate()
    {
        try {
            DB::table('threads')
                ->where('user_id', (int)$this->userId)
                ->update([
                    'long_access_token' => $this->longAccessToken,
                    'limit_date' => $this->limitDate,
                ]);
        } catch (\Exception $e) {
            report($e);
            abort(500, 'DB Error');
        }
    }
}