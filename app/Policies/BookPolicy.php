<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;

class BookPolicy
{
    public function update(User $user, Book $book): bool
    {
        return $book->owner_id === $user->id && $book->archived_at === null;
    }

    public function archive(User $user, Book $book): bool
    {
        return $book->owner_id === $user->id && $book->archived_at === null;
    }

    public function unarchive(User $user, Book $book): bool
    {
        return $book->owner_id === $user->id && $book->archived_at !== null;
    }
}
