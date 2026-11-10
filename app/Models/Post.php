<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

/**
 * Post
 *
 * Stored in MongoDB via the "mongodb" connection defined in config/database.php.
 * MongoDB stores documents (not rows), so fields like "tags" can hold a list
 * of strings natively without a pivot table.
 */
class Post extends Model
{
    // Use the MongoDB connection
    protected $connection = 'mongodb';

    // Fields that can be mass-assigned
    protected $fillable = [
        'title',
        'content',
        'tags',
    ];

    // Note: we intentionally do NOT cast "tags" to "array". The Laravel
    // "array" cast serializes to a JSON string, which would defeat one of
    // MongoDB's strengths. Without the cast, the MongoDB driver stores and
    // returns "tags" as a native BSON array (queryable, indexable).
}
