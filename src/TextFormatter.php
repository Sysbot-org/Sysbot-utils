<?php
/** @noinspection HtmlUnknownTarget */


namespace Sysbot\Utils;


use InvalidArgumentException;
use JetBrains\PhpStorm\Pure;
use stdClass;

class TextFormatter
{

    public const HTML = 1;
    public const MARKDOWN = 2;

    public const MARKUP_MAP = [
        self::HTML => [
            'bold' => '<b>%s</b>',
            'italic' => '<i>%s</i>',
            'underline' => '<u>%s</u>',
            'strikethrough' => '<s>%s</s>',
            'text_link' => '<a href="%s">%s</a>',
            'text_mention' => '<a href="tg://user?id=%s">%s</a>',
            'code' => '<code>%s</code>',
            'pre' => '<pre>%s</pre>',
            'pre_language' => '<pre><code class="language-%s">%s</code></pre>'
        ],
        self::MARKDOWN => [
            'bold' => '*%s*',
            'italic' => "_%s_\r",
            'underline' => '__%s__',
            'strikethrough' => '~%s~',
            'text_link' => '[%s](%s)',
            'text_mention' => '[%s](tg://user?id=%s)',
            'code' => '`%s`',
            'pre' => "```%s\n%s\n```"
        ]
    ];


    public function __construct(private string $text, private array $entities = [])
    {
    }

    public static function getUtf8Length(string $text): int
    {
        $length = 0;
        $chars = str_split($text);
        foreach ($chars as $char) {
            $char = ord($char);
            $length += (($char & 0xc0) != 0x80) + ($char >= 0xf0);
        }
        return $length;
    }


    #[Pure]
    public static function getUtf8Substr(string $text, int $offset, ?int $length = null): string
    {
        $textLength = self::getUtf8Length($text);
        $offset = $offset < 0 ? $textLength + $offset : $offset;
        if (null === $length) {
            $length = $textLength - $offset;
        }
        $length = $length < 0 ? $textLength - $offset + $length : $length;
        $result = '';
        $tmpOffset = 0;
        $tmpLength = 0;
        $chars = str_split($text);
        foreach ($chars as $char) {
            $char = ord($char);
            $tmpOffset += (($char & 0xc0) != 0x80) + ($char >= 0xf0);
            $tmpLength += (($char & 0xc0) != 0x80 and $tmpOffset > $offset) + ($char >= 0xf0);
            if ($tmpOffset > $offset and $tmpLength <= $length) {
                $result .= chr($char);
            }
        }
        return $result;
    }

    public function getText(): string
    {
        return $this->text;
    }

    private function convertEntities(): array
    {
        $entities = $this->entities;
        $map = [];
        usort(
            $this->entities,
            function (stdClass $a, stdClass $b) {
                if ($a->offset == $b->offset) {
                    return $a->length <=> $b->length;
                }
                return $a->offset <=> $b->offset;
            }
        );
        foreach ($entities as $entity) {
            $map[$entity->offset][$entity->length][] = $entity;
        }
        return $map;
    }

    public function getMarkup(int $markupLanguage = self::HTML): string
    {
        $result = '';
        $entitiesGroup = $this->convertEntities();
        $lastIndex = 0;
        foreach ($entitiesGroup as $index => $entities) {
            $diff = $index - $lastIndex;
            if (0 < $diff) {
                $result .= self::getUtf8Substr($this->text, $lastIndex, $diff);
            }
            $length = array_key_last($entities);
            $result .= $this->addMarkupGroup($markupLanguage, self::getUtf8Substr($this->text, $index, $length), $entities);
            $lastIndex = $index + $length;
        }
        $result .= self::getUtf8Substr($this->text, $lastIndex);
        return $result;
    }

    private function addMarkupGroup(int $markupLanguage, string $text, array $entitiesGroup): string
    {
        $result = '';
        $start = 0;
        foreach ($entitiesGroup as $length => $entities) {
            $result .= self::getUtf8Substr($text, $start, $length - $start);
            foreach ($entities as $entity) {
                $result = match ($entity->type) {
                    'text_link' => $markupLanguage == self::HTML ? $this->addMarkup(
                        $markupLanguage,
                        $entity->type,
                        $entity->url,
                        $result
                    ) : $this->addMarkup($markupLanguage, $entity->type, $result, $entity->url),
                    'text_mention' => $markupLanguage == self::HTML ? $this->addMarkup(
                        $markupLanguage,
                        $entity->type,
                        $entity->user->id,
                        $result
                    ) : $this->addMarkup($markupLanguage, $entity->type, $result, $entity->user->id),
                    'pre' => $this->addMarkup($markupLanguage, $entity->type, $result, $entity->language ?? ''),
                    default => $this->addMarkup($markupLanguage, $entity->type, $result)
                };
            }
            $start = $length;
        }
        return $result;
    }

    public function addMarkup(int $markupLanguage, string $type, string ...$text): string
    {
        $markupLanguage = array_key_exists($markupLanguage, self::MARKUP_MAP) ? $markupLanguage : self::HTML;
        $markupTemplate = self::MARKUP_MAP[$markupLanguage][$type] ?? null;
        if (empty($markupTemplate)) {
            throw new InvalidArgumentException('Invalid markup language or type');
        }
        return sprintf($markupTemplate, ...$text);
    }


}