<?php
declare(strict_types=1);

/*
 * Abstract schema for Migrations tests.
 */
return [
    // Ordered by constraint dependencies
    [
        'table' => 'categories',
        'columns' => [
            'id' => ['type' => 'integer', 'unsigned' => true],
            'parent_id' => ['type' => 'integer', 'unsigned' => true, 'length' => 11],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255],
            'slug' => ['type' => 'string', 'null' => true, 'length' => 100],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'categories_slug_unique' => ['type' => 'unique', 'columns' => ['slug']],
        ],
    ],
    [
        'table' => 'products',
        'columns' => [
            'id' => ['type' => 'integer', 'unsigned' => true],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255],
            'slug' => ['type' => 'string', 'null' => true, 'length' => 100],
            'category_id' => ['type' => 'integer', 'unsigned' => true, 'length' => 11],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'indexes' => [
            'products_title_idx' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'products_slug_unique' => ['type' => 'unique', 'columns' => ['slug']],
            'products_category_unique' => ['type' => 'unique', 'columns' => ['category_id', 'id']],
            'products_category_fk' => [
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
            'id' => ['type' => 'integer', 'unsigned' => true],
            'product_category' => ['type' => 'integer', 'unsigned' => true, 'null' => false, 'length' => 11],
            'product_id' => ['type' => 'integer', 'unsigned' => true, 'null' => false, 'length' => 11],
        ],
        'indexes' => [
            'orders_product_category_idx' => [
                'type' => 'index',
                'columns' => ['product_category', 'product_id'],
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary', 'columns' => ['id'],
            ],
            'orders_product_fk' => [
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
            'id' => ['type' => 'integer', 'unsigned' => true],
            'title' => ['type' => 'string', 'null' => true, 'length' => 255, 'comment' => 'Article title'],
            'category_id' => ['type' => 'integer', 'unsigned' => true, 'length' => 11],
            'product_id' => ['type' => 'integer', 'unsigned' => true, 'length' => 11],
            'note' => ['type' => 'string', 'default' => '7.4', 'length' => 255],
            'counter' => ['type' => 'integer', 'length' => 11, 'unsigned' => true],
            'active' => ['type' => 'boolean', 'default' => 0],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'modified' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'indexes' => [
            'articles_title_idx' => [
                'type' => 'index',
                'columns' => ['title'],
            ],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'articles_category_fk' => [
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
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['name', 'id']]],
    ],
    [
        'table' => 'events',
        'columns' => [
            'id' => ['type' => 'integer', 'unsigned' => true],
            'title' => ['type' => 'string', 'null' => true],
            'description' => 'text',
            'published' => ['type' => 'string', 'length' => 1, 'default' => 'N'],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ],
    [
        'table' => 'parts',
        'columns' => [
            'id' => ['type' => 'integer', 'unsigned' => true],
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
            'id' => ['type' => 'integer', 'unsigned' => true],
            'article_id' => ['type' => 'integer', 'unsigned' => true, 'null' => false, 'length' => 11],
            'author_id' => ['type' => 'integer', 'unsigned' => true, 'null' => true, 'length' => 11],
            'tag_id' => ['type' => 'integer', 'unsigned' => true, 'null' => false, 'length' => 11],
            'highlighted' => ['type' => 'boolean', 'null' => true],
            'highlighted_time' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id']],
            'special_tags_article_unique' => ['type' => 'unique', 'columns' => ['article_id']],
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
            'id' => ['type' => 'integer', 'unsigned' => true],
            'username' => ['type' => 'string', 'null' => true, 'length' => 256],
            'password' => ['type' => 'string', 'null' => true, 'length' => 256],
            'created' => ['type' => 'timestamp', 'null' => true, 'default' => null],
            'updated' => ['type' => 'timestamp', 'null' => true, 'default' => null],
        ],
        'constraints' => ['primary' => ['type' => 'primary', 'columns' => ['id']]],
    ],
];
