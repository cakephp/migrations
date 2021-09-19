<?php
declare(strict_types=1);

/**
 * Abstract schema for Migrations tests.
 */
return [
    // Ordered by constraint dependencies
    [
        'table' => 'categories',
        'columns' => [
            'id' => ['type' => 'integer'],
            'parent_id' => ['type' => 'integer', 'length' => 11],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255],
            'slug' => ['type' => 'string', 'null' => true, 'length' => 100],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'categories_unique_slug' => ['type' => 'unique', 'columns' => ['slug']],
        ],
    ],
    [
        'table' => 'products',
        'columns' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255],
            'slug' => ['type' => 'string', 'null' => true, 'length' => 100],
            'category_id' => ['type' => 'integer', 'length' => 11],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'indexes' => [
            'title_idx_ft' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'products_unique_slug' => ['type' => 'unique', 'columns' => ['slug']],
            'products_category_unique' => ['type' => 'unique', 'columns' => ['category_id', 'id']],
            'category_idx' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
    ],
    [
        'table' => 'orders',
        'columns' => [
            'id' => ['type' => 'integer'],
            'product_category' => ['type' => 'integer', 'null' => false, 'length' => 11],
            'product_id' => ['type' => 'integer', 'null' => false, 'length' => 11],
        ],
        'indexes' => [
            'product_category' => [
                'type' => 'index',
                'columns' => ['product_category', 'product_id'],
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary', 'columns' => ['id'],
            ],
            'product_id_fk' => [
                'type' => 'foreign',
                'columns' => ['product_category', 'product_id'],
                'references' => ['products', ['category_id', 'id']],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
    ],
    [
        'table' => 'articles',
        'columns' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255, 'comment' => 'Article title'],
            'category_id' => ['type' => 'integer', 'length' => 11],
            'product_id' => ['type' => 'integer', 'length' => 11],
            'note' => ['type' => 'string', 'default' => '7.4', 'length' => 255],
            'counter' => ['type' => 'integer', 'length' => 11, 'unsigned' => true],
            'active' => ['type' => 'boolean', 'default' => 0],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'indexes' => [
            'title_idx' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'category_article_idx' => [
                'type' => 'foreign',
                'columns' => ['category_id'],
                'references' => ['categories', 'id'],
                'update' => 'noAction',
                'delete' => 'noAction',
            ],
        ],
    ],
    [
        'table' => 'composite_pks',
        'columns' => [
            'id' => ['type' => 'uuid', 'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7', 'null' => false ],
            'name' => ['type' => 'string', 'length' => 10, 'default' => '', 'null' => false],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id', 'name']]],
    ],
    [
        'table' => 'events',
        'columns' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string', 'null' => true],
            'description' => 'text',
            'published' => ['type' => 'string', 'length' => 1, 'default' => 'N'],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ],
    [
        'table' => 'parts',
        'columns' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'length' => 255],
            'number' => ['type' => 'integer', 'null' => true, 'length' => 10, 'unsigned' => true],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary', 'columns' => ['id'],
            ],
        ],
    ],
    [
        'table' => 'special_pks',
        'columns' => [
            'id' => ['type' => 'uuid', 'default' => 'a4950df3-515f-474c-be4c-6a027c1957e7'],
            'name' => ['type' => 'string', 'null' => true, 'length' => 256],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ],
    [
        'table' => 'special_tags',
        'columns' => [
            'id' => ['type' => 'integer'],
            'article_id' => ['type' => 'integer', 'null' => false, 'length' => 11],
            'author_id' => ['type' => 'integer', 'null' => true, 'length' => 11],
            'tag_id' => ['type' => 'integer', 'null' => false, 'length' => 11],
            'highlighted' => ['type' => 'boolean', 'null' => true],
            'highlighted_time' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'UNIQUE_TAG2' => ['type' => 'unique', 'columns' => ['article_id']],
        ],
    ],
    [
        'table' => 'texts',
        'columns' => [
            'title' => ['type' => 'string'],
            'description' => 'text',
        ],
    ],
    [
        'table' => 'users',
        'columns' => [
            'id' => ['type' => 'integer'],
            'username' => ['type' => 'string', 'null' => true, 'length' => 256],
            'password' => ['type' => 'string', 'null' => true, 'length' => 256],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'updated' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ],
];
