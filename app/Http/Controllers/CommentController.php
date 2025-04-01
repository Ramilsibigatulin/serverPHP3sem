<?php

namespace App\Http\Controllers;

use App\Jobs\VeryLongJob;
use App\Notifications\NewCommentNotify;
use Illuminate\Http\Request;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CommentController extends Controller
{
    public function index()
    {
        $page = request('page', 0);
        $comments = Cache::remember("comments{$page}", 3000, function () {
            return Comment::latest()->paginate(10);
        });
        return view('comments.index', ['comments' => $comments]);
    }

    public function accept($id)
{
    Log::info("Trying to accept comment with ID: " . $id);

    $comment = Comment::find($id);

    if (!$comment) {
        Log::error("Comment not found: " . $id);
        return abort(404, "Comment not found");
    }

    // Устанавливаем комментарий как одобренный
    $comment->accept = true;

    // Пробуем сохранить изменения
    if ($comment->save()) {
        Log::info("Comment with ID {$id} has been accepted and saved.");
    } else {
        Log::error("Failed to save comment with ID: " . $id);
    }

    // Очистка кеша для обновленного комментария
    $this->clearCommentCache($comment);

    // Отправка уведомлений пользователям
    $users = User::where('id', '!=', $comment->user_id)->get();
    Notification::send($users, new NewCommentNotify($comment->article, $comment->name));

    return redirect()->route('comments.index');
}


    public function reject($id)
    {
        Cache::flush();
        $comment = Comment::findOrFail($id);
        $comment->accept = false;
        $comment->save();

        return redirect()->route('comments.index');
    }

    public function store(Request $request)
    {
        $this->clearAllCommentCache();

        $request->validate([
            'name' => 'required|min:3',
            'desc' => 'required|max:256'
        ]);

        $comment = new Comment();
        $comment->name = $request->name;
        $comment->desc = $request->desc;
        $comment->article_id = $request->article_id;
        $comment->user_id = Auth::id();

        if ($comment->save()) {
            VeryLongJob::dispatch($comment);
            return redirect()->back()->with('status', 'Comment sent to moderation');
        }

        return redirect()->back();
    }

    public function edit($id)
    {
        $comment = Comment::findOrFail($id);
        $this->authorize('update-comment', $comment);

        return view('comments.update', ['comment' => $comment]);
    }

    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $this->clearCommentCache($comment);

        $this->authorize('update-comment', $comment);

        $request->validate([
            'name' => 'required|min:3',
            'desc' => 'required|max:256'
        ]);

        $comment->name = $request->name;
        $comment->desc = $request->desc;
        $comment->save();

        return redirect()->route('articles.show', ['article' => $comment->article_id]);
    }

    public function delete($id)
    {
        Cache::flush();
        $comment = Comment::findOrFail($id);
        $this->authorize('update-comment', $comment);

        $comment->delete();

        return redirect()->route('articles.show', ['article' => $comment->article_id])
            ->with('status', 'Delete success');
    }

    private function clearCommentCache(Comment $comment)
    {
        $keys = DB::table('cache')
            ->whereRaw('key GLOB :key', [':key' => 'comments*[0-9]'])
            ->get();

        foreach ($keys as $param) {
            Cache::forget($param->key);
        }

        $keys = DB::table('cache')
            ->whereRaw('key GLOB :key', [':key' => 'comment_article' . $comment->article_id])
            ->get();

        foreach ($keys as $param) {
            Cache::forget($param->key);
        }
    }

    private function clearAllCommentCache()
    {
        Cache::flush();
    }
}
