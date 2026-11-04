<?php

namespace App\Notifications;

use App\Enums\TeamsMessageLevel;

class TeamsNotification
{
    protected readonly string $title;

    protected readonly array $content;

    protected readonly TeamsMessageLevel $level;

    public function __construct(
        string $title,
        array $content,
        ?TeamsMessageLevel $level = null,
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->level = $level ?? TeamsMessageLevel::INFO;
    }

    /**
     * Build the Adaptive Card payload for Teams webhook.
     */
    public function toAdaptiveCard(): array
    {
        $facts = [];
        foreach ($this->content as $key => $value) {
            $facts[] = [
                'title' => $key,
                'value' => jsonEncode(is_array($value) ? $value : (string) $value),
            ];
        }

        return [
            'type' => 'message',
            'attachments' => [
                [
                    'contentType' => 'application/vnd.microsoft.card.adaptive',
                    'contentUrl' => null,
                    'content' => [
                        '$schema' => 'http://adaptivecards.io/schemas/adaptive-card.json',
                        'type' => 'AdaptiveCard',
                        'version' => '1.4',
                        'msteams' => [
                            'width' => 'Full',
                        ],
                        'body' => [
                            [
                                'type' => 'Container',
                                'style' => $this->getContainerStyle(),
                                'bleed' => true,
                                'items' => [
                                    [
                                        'type' => 'TextBlock',
                                        'text' => sprintf(
                                            '[%s] %s',
                                            strtoupper(config('app.env')),
                                            $this->title
                                        ),
                                        'weight' => 'bolder',
                                        'size' => 'medium',
                                        'wrap' => true,
                                        'color' => $this->level->toAdaptiveCardColor(),
                                    ],
                                ],
                            ],
                            [
                                'type' => 'TextBlock',
                                'text' => 'DETAILS:',
                                'weight' => 'bolder',
                                'spacing' => 'medium',
                            ],
                            [
                                'type' => 'FactSet',
                                'facts' => $facts,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get the container style based on message level.
     */
    protected function getContainerStyle(): string
    {
        return match ($this->level) {
            TeamsMessageLevel::ERROR => 'attention',
            TeamsMessageLevel::WARNING => 'warning',
            TeamsMessageLevel::SUCCESS => 'good',
            TeamsMessageLevel::INFO => 'default',
        };
    }

    /**
     * Get the JSON string for the webhook payload.
     */
    public function toJson(): string
    {
        return json_encode($this->toAdaptiveCard());
    }
}
