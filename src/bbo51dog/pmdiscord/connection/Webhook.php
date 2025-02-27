<?php

declare(strict_types = 1);

namespace bbo51dog\pmdiscord\connection;

use bbo51dog\pmdiscord\element\Element;
use bbo51dog\pmdiscord\exception\PMDiscordAPIException;
use bbo51dog\pmdiscord\task\SendAsyncTask;
use pocketmine\Server;

class Webhook {

    /** @var array */
    private array $data;

    /** @var string */
    private string $webhook_url;

    public function __construct(string $webhook_url) {
        $this->webhook_url = $webhook_url;
    }

    /**
     * @param string $webhook_url
     * @return self
     */
    public static function create(string $webhook_url) : self {
        return new Webhook($webhook_url);
    }

    /**
     * Add element
     *
     * @param Element $element
     * @return $this
     */
    public function add(Element $element) : self {
        $this->data[$element->getType()] = $element->getData();
        return $this;
    }

    /**
     * @return array
     */
    public function getData() : array {
        return $this->data;
    }

    /**
     * Send Message to Discord
     *
     * @param bool $async
     * @return void
     * @throws PMDiscordAPIException
     */
    public function send(bool $async = true) : void {
        if ($async) {
            Server::getInstance()->getAsyncPool()->submitTask(new SendAsyncTask($this));
            return;
        }
        $this->sender($this, false);
    }

    /**
     * @param Webhook $webhook
     * @param bool    $async
     * @return void
     * @throws PMDiscordAPIException
     */
    public function sender(Webhook $webhook, bool $async = true) : void {
        if ($async) {
            Server::getInstance()->getAsyncPool()->submitTask(new SendAsyncTask($webhook));
            return;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook->getUrl());
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhook->getData()));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) {
            throw new PMDiscordAPIException('cURLの疎通に失敗しました');
        } elseif (!empty($result)) {
            $result_array = json_decode($result, true);
            if (in_array('code', $result_array, true) && in_array('message', $result_array, true)) {
                throw new PMDiscordAPIException("{$result_array['code']} : {$result_array['message']}");
            } else {
                throw new PMDiscordAPIException('WebHookを正常に送信できませんでした');
            }
        }
    }

    /**
     * Get webhook url
     */
    public function getUrl() : string {
        return $this->webhook_url;
    }

    /**
     * Change custom senders name
     *
     * @param string $name
     * @return $this
     */
    public function setCustomName(string $name) : self {
        $this->data['username'] = $name;
        return $this;
    }

    /**
     * Set custom senders avatar url
     *
     * @param string $url
     * @return $this
     */
    public function setCustomAvatar(string $url) : self {
        $this->data['avatar_url'] = $url;
        return $this;
    }

    /**
     * Enable|Disable tts message
     *
     * @param bool $tts
     * @return $this
     */
    public function setTts(bool $tts = true) : self {
        $this->data['tts'] = $tts;
        return $this;
    }
}
