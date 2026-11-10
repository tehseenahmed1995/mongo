<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;

/**
 * CRUD controller for the Post model, backed by MongoDB.
 *
 * Routes are wired up in routes/api.php via Route::apiResource('posts', ...),
 * which exposes:
 *   GET    /api/posts        -> index
 *   POST   /api/posts        -> store
 *   GET    /api/posts/{id}   -> show
 *   PUT    /api/posts/{id}   -> update
 *   DELETE /api/posts/{id}   -> destroy
 */
class PostController extends Controller
{
    /**
     * List all posts (newest first).
     */
    public function index()
    {
        return Post::latest('created_at')->get();
    }

    /**
     * Create a new post.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
        ]);

        $post = Post::create($data);

        return response()->json($post, 201);
    }

    /**
     * Show a single post by its MongoDB _id.
     */
    public function show(string $id)
    {
        return Post::findOrFail($id);
    }

    /**
     * Update an existing post.
     */
    public function update(Request $request, string $id)
    {
        $post = Post::findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string'],
        ]);

        $post->update($data);

        return $post;
    }

    /**
     * Delete a post.
     */
    public function destroy(string $id)
    {
        $post = Post::findOrFail($id);
        $post->delete();

        return response()->noContent();
    }
}
