<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    public function view(User $user, Book $book): Response
    {
        if ($book->archived_at === null || $book->owner_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

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
