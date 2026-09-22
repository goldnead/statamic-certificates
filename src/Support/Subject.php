<?php

namespace Goldnead\Certificates\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Statamic\Auth\User as StatamicUser;
use Statamic\Facades\User;

/**
 * Who a certificate belongs to, as the (type, id) pair the table keys on.
 *
 * **A site user is `user` plus the auth identifier**, whether the caller holds
 * a Statamic user, the auth guard's Eloquent model or just the id. That is the
 * same id statamic-courses keys progress on and puts on CourseCompleted, so a
 * certificate issued from the event and one issued by hand for the same person
 * collide on the unique index, as they must.
 *
 * Any other Eloquent model (a learner model that is not the site's user) is
 * keyed by its morph class and primary key.
 */
final class Subject
{
    public const USER = 'user';

    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $email,
    ) {}

    public static function of(mixed $subject): self
    {
        if ($subject instanceof StatamicUser) {
            return new self(self::USER, (string) $subject->getAuthIdentifier(), self::nameOf($subject), $subject->email());
        }

        if ($subject instanceof Authenticatable) {
            $id = (string) $subject->getAuthIdentifier();

            // Resolved through Statamic, so name and email read the same way
            // as for a file-based user.
            $user = User::find($id);

            return $user
                ? self::of($user)
                : new self(self::USER, $id, self::modelName($subject), self::modelEmail($subject));
        }

        if ($subject instanceof Model) {
            return new self($subject->getMorphClass(), (string) $subject->getKey(), self::modelName($subject), self::modelEmail($subject));
        }

        if ((is_string($subject) && $subject !== '') || is_int($subject)) {
            $user = User::find((string) $subject);

            if ($user) {
                return self::of($user);
            }
        }

        throw new InvalidArgumentException('A certificate needs a subject: pass a user, an Eloquent model, or the id of an existing user.');
    }

    /**
     * The subject a stored (type, id) pair points at, for mail delivery.
     */
    public static function find(string $type, string $id): ?self
    {
        if ($type === self::USER) {
            $user = User::find($id);

            return $user ? self::of($user) : null;
        }

        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        $model = $class::query()->find($id);

        return $model instanceof Model ? self::of($model) : null;
    }

    /**
     * The currently signed-in user as a subject, or null for a guest.
     */
    public static function current(): ?self
    {
        $user = User::current();

        return $user ? self::of($user) : null;
    }

    public function is(string $type, string $id): bool
    {
        return $this->type === $type && $this->id === $id;
    }

    private static function nameOf(StatamicUser $user): ?string
    {
        $name = $user->name();

        return is_string($name) && trim($name) !== '' && $name !== $user->email() ? trim($name) : null;
    }

    private static function modelName(Model|Authenticatable $model): ?string
    {
        $name = $model instanceof Model ? $model->getAttribute('name') : null;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    private static function modelEmail(Model|Authenticatable $model): ?string
    {
        $email = $model instanceof Model ? $model->getAttribute('email') : null;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
