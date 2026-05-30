<?php

declare(strict_types=1);

namespace App\Assistant\Application\Command;

use App\Assistant\Application\Dto\SendMessageResult;
use App\Assistant\Domain\Exception\InvalidArgument;
use App\Assistant\Domain\Exception\SessionNotFound;
use App\Assistant\Domain\Model\Message;
use App\Assistant\Domain\Model\ValueObject\MessageContent;
use App\Assistant\Domain\Model\ValueObject\MessageRole;
use App\Assistant\Domain\Port\Clock;
use App\Assistant\Domain\Port\IdGenerator;
use App\Assistant\Domain\Port\LlmPort;
use App\Assistant\Domain\Port\MessageRepository;
use App\Assistant\Domain\Port\SessionRepository;

final readonly class SendMessageHandler
{
    public function __construct(
        private SessionRepository $sessions,
        private MessageRepository $messages,
        private LlmPort $llm,
        private IdGenerator $ids,
        private Clock $clock,
    ) {
    }

    public function __invoke(SendMessageCommand $command): SendMessageResult
    {
        if ('' === trim($command->userText)) {
            throw new InvalidArgument('User message cannot be empty.');
        }

        $session = $this->sessions->findById($command->sessionId);
        if (null === $session) {
            throw SessionNotFound::withId($command->sessionId);
        }

        $userMessage = new Message(
            $this->ids->nextMessageId(),
            $command->sessionId,
            MessageRole::User,
            MessageContent::of($command->userText),
            $this->clock->now(),
        );
        $this->messages->append($userMessage);

        $history = $this->messages->forSession($command->sessionId);
        $reply = $this->llm->complete($session->model, $history);

        $assistantMessage = new Message(
            $this->ids->nextMessageId(),
            $command->sessionId,
            MessageRole::Assistant,
            MessageContent::of($reply->content),
            $this->clock->now(),
        );
        $this->messages->append($assistantMessage);

        $session->touch($this->clock->now());
        $this->sessions->save($session);

        return new SendMessageResult(
            $userMessage,
            $assistantMessage,
            $reply->promptTokens,
            $reply->completionTokens,
        );
    }
}
