<?php

/*
 * This file is part of fof/webhooks.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\Webhooks\Adapters\Ntfy;

use FoF\Webhooks\Response;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\GuzzleException;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\Approval\Event\PostWasApproved;
use Flarum\Discussion\Event\Started;

class Adapter extends \FoF\Webhooks\Adapters\Adapter
{
    /**
     * {@inheritdoc}
     */
    const NAME = 'ntfy';

    /**
     * {@inheritdoc}
     */
    protected $exception = NtfyException::class;

    /**
     * Sends a message through the webhook.
     *
     * @param string   $url
     * @param Response $response
     *
     * @throws GuzzleException
     */
    public function send(string $url, Response $response)
    {
        // 提取ntfy主题
        $lastSlashPos = strrpos($url, '/');

        if ($lastSlashPos !== false) {
            $domain = substr($url, 0, $lastSlashPos);
            $topic = substr($url, $lastSlashPos + 1);
        } else {
            $domain = $url;
            $topic = 'flarum';
        }  

        $payload = $this->toArray($response);
        $payload['topic'] = $topic;

        // Explicitly set headers to ensure ntfy parses the payload as JSON.
        $headers = [
            'Content-Type' => 'application/json',
            // 'X-Title'      => Str::limit($this->getTitle($response), 256),
        ];

        // We use 'body' with manual json_encode instead of 'json' shorthand for robustness.
        $this->client->request('POST', $domain, [
            'body'            => json_encode($payload),
            'headers'         => $headers,
        ]);
    }


    public function toArray(Response $response): array
    {
        $event = $response->event;
        $finalTitle = '';
        $finalMessage = '';

        // Handle Post-related events
        if ($event instanceof Posted || $event instanceof Revised || $event instanceof PostWasApproved) {
            $post = $event->post;
            $discussion = $post->discussion;
            $user = $post->user;
            
            $finalTitle = "💬 新回复: " . $discussion->title;
            
            // 构建更丰富的消息内容
            $finalMessage = sprintf(
                "👤 %s 回复了主题\n\n" .
                "📝 回复内容：\n%s\n\n" .
                "🏷️ 标签：%s\n" .
                "⏰ 时间：%s\n\n" .
                "🔗 查看详情：%s",
                $user->nickname ?: $user->username,
                $post->content,
                $discussion->tags->pluck('name')->join(', '),
                $post->created_at,
                $response->url
            );
        } 
        // Handle new Discussion events
        else if ($event instanceof Started) {
            $discussion = $event->discussion;
            $user = $discussion->user;
            
            $finalTitle = "📢 新主题: " . $discussion->title;
            
            $finalMessage = sprintf(
                "👤 %s 发布了新主题\n\n" .
                "📝 主题内容：\n%s\n\n" .
                "🏷️ 标签：%s\n" .
                "⏰ 时间：%s\n\n" .
                "🔗 查看详情：%s",
                $user->nickname ?: $user->username,
                $response->description,
                $discussion->tags->pluck('name')->join(', '),
                $discussion->created_at,
                $response->url
            );
        } 
        // Fallback for other events
       else {
            $finalTitle = $response->title;
            $finalMessage = $response->description;
        }

        // Prepend extra text if it exists
        if ($extraText = $response->getExtraText()) {
            $finalMessage = $extraText . "\n\n" . $finalMessage;
        }

        return [
            'title'   => Str::limit($finalTitle, 256),
            'message' => Str::limit($finalMessage, 4096) ?: null,
            'click'   => $response->url,
            'tags'    => $response->getIncludeTags() ? $this->getTagsAsEmojis($response) : [],
        ];
    }

    /**
     * @param Response $response
     *
     * @return array
     */
    protected function getTagsAsEmojis(Response $response): array
    {
        $tagMap = [
            // Example: 'support' => 'sos', 'news' => 'mega'
        ];

        $emojis = [];
        foreach ($response->getTags() as $tagName) {
            $slug = Str::slug($tagName);
            if (isset($tagMap[$slug])) {
                $emojis[] = $tagMap[$slug];
            }
        }

        if (empty($emojis)) {
            $emojis[] = 'speech_balloon';
        }

        return $emojis;
    }

    /**
     * @param string $url
     *
     * @return bool
     */
    public static function isValidURL(string $url): bool
    {
        return (bool) preg_match('/^https?:\/\/[^\/]+\/[^\/]+$/', $url);
    }
}