<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Osama\LaravelExports\Exports\ExportColumn;

describe('HasCellState Trait', function () {
    it('can set and get default state', function () {
        $column = ExportColumn::make('name')->default('Default Name');

        expect($column->getDefaultState())->toBe('Default Name');
    });

    it('can set and get separator', function () {
        $column = ExportColumn::make('tags')->separator(';');

        expect($column->getSeparator())->toBe(';');
    });

    it('can set and check distinct list', function () {
        $column = ExportColumn::make('tags')->distinctList(true);

        expect($column->isDistinctList())->toBeTrue();
    });

    it('can check if has relationship', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $column = ExportColumn::make('posts.title');
        expect($column->hasRelationship($model))->toBeTrue();

        $column2 = ExportColumn::make('name');
        expect($column2->hasRelationship($model))->toBeFalse();
    });

    it('can get relationship', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $column = ExportColumn::make('posts.title');
        $relationship = $column->getRelationship($model);

        expect($relationship)->toBeInstanceOf(HasMany::class);
    });

    it('can check if has multiple relationship', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $column = ExportColumn::make('posts.title');
        $model->setRelation('posts', Collection::make([new PostModel]));

        expect($column->hasMultipleRelationship($model))->toBeTrue();
    });

    it('can get relationship results', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $post = new PostModel;
        $model->setRelation('posts', Collection::make([$post]));

        $column = ExportColumn::make('posts.title');
        $results = $column->getRelationshipResults($model);

        expect($results)->toBeArray()
            ->and(count($results))->toBeGreaterThan(0);
    });

    it('can get attribute name', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $column = ExportColumn::make('name');
        expect($column->getAttributeName($model))->toBe('name');

        $column2 = ExportColumn::make('posts.title');
        // When relationship doesn't exist, it returns the full path
        expect($column2->getAttributeName($model))->toBe('title');
    });

    it('can get full attribute name', function () {
        $model = new class extends Model {};

        $column = ExportColumn::make('posts.title');
        expect($column->getFullAttributeName($model))->toBe('posts.title');
    });

    it('can get relationship name', function () {
        $model = new class extends Model
        {
            public function posts()
            {
                return $this->hasMany(PostModel::class);
            }
        };

        $column = ExportColumn::make('posts.title');
        expect($column->getRelationshipName($model))->toBe('posts');

        $column2 = ExportColumn::make('name');
        expect($column2->getRelationshipName($model))->toBeNull();
    });
});

class PostModel extends Model
{
    protected $table = 'posts';
}
