<?php

namespace App\Notifications;

use App\Enums\SlackMessageLevel;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SlackNotification extends Notification
{
    protected string $title;

    protected array $content;

    protected ?SlackMessageLevel $level;

    public function __construct(
        string $title,
        array $content,
        ?SlackMessageLevel $level = null,
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->level = $level;
    }

    /**
     * @return string[]
     */
    public function via($notifiable)
    {
        return ['slack'];
    }

    /**
     * @return array
     */
    public function toArray($notifiable)
    {
        return [];
    }

    /**
     * @return SlackMessage
     */
    public function toSlack($notifiable)
    {
        $slackMessage = (new SlackMessage());

        $slackMessage->level = $this->level->value;

        return $slackMessage
            ->content(sprintf(
                '[%s] %s',
                strtoupper(config('app.env')),
                $this->title
            ))
            ->attachment(function ($attachment) {
                $attachment->title('DETAILS:')
                    ->fields($this->content);
            });
    }
}
