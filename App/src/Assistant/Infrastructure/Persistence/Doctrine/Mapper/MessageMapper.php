<?php

declare(strict_types=1);

namespace App\Assistant\Infrastructure\Persistence\Doctrine\Mapper;

use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageId;
use App\Assistant\Domain\Model\ValueObject\MessagePayload;
use App\Assistant\Domain\Model\ValueObject\MessagePayloadKind;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Model\ValueObject\SessionId;
use App\Assistant\Domain\Model\ValueObject\ToolCallRequest;
use App\Assistant\Infrastructure\Persistence\Doctrine\Entity\MessageEntity;

final class MessageMapper
{
    public function toEntity(Message $message): MessageEntity
    {
        $entity = new MessageEntity();
        $entity->id = $message->id->value;
        $entity->sessionId = $message->sessionId->value;
        $entity->role = $message->role->value;
        $entity->content = $message->content->text;
        $entity->createdAt = $message->createdAt;
        $entity->payloadJson = null === $message->payload ? null : $this->encodePayload($message->payload);

        return $entity;
    }

    public function toAggregate(MessageEntity $entity): Message
    {
        return new Message(
            MessageId::fromString($entity->id),
            SessionId::fromString($entity->sessionId),
            MessageRole::from($entity->role),
            MessageContent::of($entity->content),
            $entity->createdAt,
            null === $entity->payloadJson ? null : $this->decodePayload($entity->payloadJson),
        );
    }

    private function encodePayload(MessagePayload $payload): string
    {
        $data = match ($payload->kind) {
            MessagePayloadKind::ToolCall => [
                'kind' => $payload->kind->value,
                'tool_calls' => array_map(
                    static fn (ToolCallRequest $r): array => [
                        'id' => $r->id,
                        'name' => $r->name,
                        'arguments' => $r->arguments,
                    ],
                    $payload->toolCalls,
                ),
            ],
            MessagePayloadKind::ToolResult => [
                'kind' => $payload->kind->value,
                'tool_call_id' => $payload->toolCallId,
                'tool_name' => $payload->toolName,
                'output' => $payload->toolOutput,
                'is_error' => $payload->isError,
            ],
        };

        return json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }

    private function decodePayload(string $json): MessagePayload
    {
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($decoded));
        /** @var array<string, mixed> $data */
        $data = $decoded;
        $kindRaw = $data['kind'] ?? '';
        \assert(\is_string($kindRaw));
        $kind = MessagePayloadKind::from($kindRaw);

        if (MessagePayloadKind::ToolCall === $kind) {
            /** @var list<array{id: string, name: string, arguments: array<string, mixed>}> $raw */
            $raw = $data['tool_calls'] ?? [];

            return MessagePayload::ofToolCalls(array_map(
                static fn (array $r): ToolCallRequest => new ToolCallRequest(
                    id: $r['id'],
                    name: $r['name'],
                    arguments: $r['arguments'],
                ),
                $raw,
            ));
        }

        $toolCallId = $data['tool_call_id'] ?? '';
        $toolName = $data['tool_name'] ?? '';
        $output = $data['output'] ?? '';
        \assert(\is_string($toolCallId) && \is_string($toolName) && \is_string($output));

        return MessagePayload::ofToolResult(
            toolCallId: $toolCallId,
            toolName: $toolName,
            output: $output,
            isError: (bool) ($data['is_error'] ?? false),
        );
    }
}
