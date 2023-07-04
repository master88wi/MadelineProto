<?php declare(strict_types=1);

namespace danog\MadelineProto\EventHandler;

use danog\MadelineProto\EventHandler\Keyboard\InlineKeyboard;
use danog\MadelineProto\EventHandler\Keyboard\ReplyKeyboard;
use danog\MadelineProto\MTProto;
use danog\MadelineProto\StrTools;

/**
 * Represents an incoming or outgoing message.
 */
abstract class Message extends Update
{
    /** Message ID */
    public readonly int $id;
    /** Content of the message */
    public readonly string $message;
    /** ID of the chat where the message was sent */
    public readonly int $chatId;
    /** ID of the message to which this message is replying */
    public readonly ?int $replyToMsgId;
    /** When was the message sent */
    public readonly int $date;

    /** Info about a forwarded message */
    public readonly ?ForwardedInfo $fwdInfo;

    /** ID of the forum topic where the message was sent */
    public readonly ?int $topicId;

    /** ID of the message thread where the message was sent */
    public readonly ?int $threadId;

    /** Whether this is a reply to a scheduled message */
    public readonly bool $replyToScheduled;
    /** Whether we were mentioned in this message */
    public readonly bool $mentioned;
    /** Whether this message was sent without any notification (silently) */
    public readonly bool $silent;
    /** Whether this message is a sent scheduled message */
    public readonly bool $fromScheduled;
    /** Whether this message is a pinned message */
    public readonly bool $pinned;
    /** Whether this message is protected (and thus can't be forwarded or downloaded) */
    public readonly bool $protected;
    /** If the message was generated by an inline query, ID of the bot that generated it */
    public readonly ?int $viaBotId;

    /** Last edit date of the message */
    public readonly ?int $editDate;

    /** Time-to-live of the message */
    public readonly ?int $ttlPeriod;

    /** Inline or reply keyboard. */
    public readonly InlineKeyboard|ReplyKeyboard|null $keyboard;

    /** Whether this message was [imported from a foreign chat service](https://core.telegram.org/api/import) */
    public readonly bool $imported;

    /** For Public Service Announcement messages, the PSA type */
    public readonly string $psaType;

    // Todo media, albums, reactions, replies

    /** @internal */
    protected function __construct(
        MTProto $API,
        array $rawMessage,
        /** Whether the message is outgoing */
        public readonly bool $out
    ) {
        parent::__construct($API);
        $info = $this->API->getInfo($rawMessage);

        $this->entities = $rawMessage['entities'] ?? null;
        $this->id = $rawMessage['id'];
        $this->message = $rawMessage['message'] ?? '';
        $this->chatId = $info['bot_api_id'];
        $this->date = $rawMessage['date'];
        $this->mentioned = $rawMessage['mentioned'];
        $this->silent = $rawMessage['silent'];
        $this->fromScheduled = $rawMessage['from_scheduled'];
        $this->pinned = $rawMessage['pinned'];
        $this->protected = $rawMessage['noforwards'];
        $this->viaBotId = $rawMessage['via_bot_id'] ?? null;
        $this->editDate = $rawMessage['edit_date'] ?? null;
        $this->ttlPeriod = $rawMessage['ttl_period'] ?? null;

        $this->keyboard = isset($rawMessage['reply_markup'])
            ? Keyboard::fromRawReplyMarkup($rawMessage['reply_markup'])
            : null;

        if (isset($rawMessage['reply_to'])) {
            $replyTo = $rawMessage['reply_to'];
            $this->replyToScheduled = $replyTo['reply_to_scheduled'];
            if ($replyTo['forum_topic']) {
                if (isset($replyTo['reply_to_top_id'])) {
                    $this->topicId = $replyTo['reply_to_top_id'];
                    $this->replyToMsgId = $replyTo['reply_to_msg_id'];
                } else {
                    $this->topicId = $replyTo['reply_to_msg_id'];
                    $this->replyToMsgId = null;
                }
                $this->threadId = null;
            } elseif ($info['Chat']['forum'] ?? false) {
                $this->topicId = 1;
                $this->replyToMsgId = $replyTo['reply_to_msg_id'];
                $this->threadId = $replyTo['reply_to_top_id'] ?? null;
            } else {
                $this->topicId = null;
                $this->replyToMsgId = $replyTo['reply_to_msg_id'];
                $this->threadId = $replyTo['reply_to_top_id'] ?? null;
            }
        } elseif ($info['Chat']['forum'] ?? false) {
            $this->topicId = 1;
            $this->replyToMsgId = null;
            $this->threadId = null;
            $this->replyToScheduled = false;
        } else {
            $this->topicId = null;
            $this->replyToMsgId = null;
            $this->threadId = null;
            $this->replyToScheduled = false;
        }

        if (isset($rawMessage['fwd_from'])) {
            $fwdFrom = $rawMessage['fwd_from'];
            $this->fwdInfo = new ForwardedInfo(
                $fwdFrom['date'],
                isset($fwdFrom['from_id'])
                    ? $this->API->getIdInternal($fwdFrom['from_id'])
                    : null,
                $fwdFrom['from_name'] ?? null,
                $fwdFrom['channel_post'] ?? null,
                $fwdFrom['post_author'] ?? null,
                isset($fwdFrom['saved_from_peer'])
                    ? $this->API->getIdInternal($fwdFrom['saved_from_peer'])
                    : null,
                $fwdFrom['saved_from_msg_id'] ?? null
            );
            $this->psaType = $fwdFrom['psa_type'] ?? null;
        } else {
            $this->fwdInfo = null;
            $this->psaType = null;
        }
    }

    private readonly string $html;
    private readonly string $htmlTelegram;
    private readonly ?array $entities;
    /**
     * Get an HTML version of the message.
     *
     * @param bool $allowTelegramTags Whether to allow telegram-specific tags like tg-spoiler, tg-emoji, mention links and so on...
     */
    public function getHTML(bool $allowTelegramTags = false): string
    {
        if (!$this->entities) {
            return \htmlentities($this->message);
        }
        if ($allowTelegramTags) {
            return $this->htmlTelegram ??= StrTools::messageEntitiesToHtml($this->message, $this->entities, $allowTelegramTags);
        }
        return $this->html ??= StrTools::messageEntitiesToHtml($this->message, $this->entities, $allowTelegramTags);
    }
}
