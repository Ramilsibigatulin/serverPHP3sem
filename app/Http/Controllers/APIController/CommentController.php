<?php

namespace App\Http\Controllers\APIController;

use App\Jobs\VeryLongJob;
use App\Notifications\NewCommentNotify;
use Auth;
use Gate;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Mail\NewCommentMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CommentController extends Controller
{
    public function index()
    {
        $page = isset($_GET['page']) ? $_GET['page'] : 0;
        $comments = Cache::remember('comments'.$page, 3000, function(){
            return Comment::latest()->paginate(10);
        });
        return response()->json($comments);
    }
    public function accept(Comment $comment) {
        $keys = DB::table('cache')->whereRaw('`key` GLOB :key', [':key'=>'comments*[0-9]'])->get();
        foreach($keys as $param){
            Cache::forget($param->key);
        }
        $keys = DB::table('cache')->whereRaw('`key` GLOB :key', [':key'=>'comment_article'.$comment->article_id])->get();
        foreach($keys as $param){
            Cache::forget($param->key);
        }

        $comment->accept = true;
        $users = User::where('id', '!=', $comment->user_id)->get();
        if ($comment->save()) {
            Notification::send($users, new NewCommentNotify($comment->article, $comment->name));
        };
        return response()->json($comment);
    }
    public function reject(Comment $comment) {
        Cache::flush();
        $comment->accept = false;
        $comment->save();
        return response()->json(1);
    }
    public function store(Request $request)
    {
        $keys = DB::table('cache')->whereRaw('`key` GLOB :key', [':key'=>'comments*[0-9]'])->get();
        foreach($keys as $param){
            Cache::forget($param->key);
        }
        $request->validate([
            'name' => 'required|min:3',
            'desc' => 'required|max:256'
        ]);
        $comment = new Comment;
        $comment->name = request('name');
        $comment->desc = request('desc');
        $comment->article_id = request('article_id');
        $comment->user_id = Auth::id();
        if ($comment->save()){
            VeryLongJob::dispatch($comment);
            return response()->json(1);
            } else {
                return response()->json(0);
            }
    }

    public function edit($id)
    {
        $comment = Comment::findOrFail($id);
        Gate::authorize('update-comment', ['comment' => $comment]);
        return view('comments.update', ['comment' => $comment]);
    }

    public function update(Request $request, Comment $comment)
    {
        $keys = DB::table('cache')->whereRaw('`key` GLOB :key', [':key'=>'comments*[0-9]'])->get();
        foreach($keys as $param){
            Cache::forget($param->key);
        }
        $keys = DB::table('cache')->whereRaw('`key` GLOB :key', [':key'=>'comment_article'.$comment->article_id])->get();
        foreach($keys as $param){
            Cache::forget($param->key);
        }
        Gate::authorize('update-comment', ['comment' => $comment]);
        $request->validate([
            'name' => 'required|min:3',
            'desc' => 'required|max:256'
        ]);
        $comment->name = request('name');
        $comment->desc = request('desc');
        $comment->save();
        return redirect()->route('articles.show', ['article' => $comment->article_id]);
    }

    public function delete($id)
    {
        Cache::flush();
        $comment = Comment::findOrFail($id);
        Gate::authorize('update-comment', ['comment' => $comment]);
        $comment->delete();
        return redirect()->route('articles.show', ['article' => $comment->article_id])->with('status', 'Delete success');
    }
}
