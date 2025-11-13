<?php
declare(strict_types=1);

use Migrations\BaseMigration;

/**
 * Example migration demonstrating views and triggers support.
 *
 * This migration shows how to create and drop database views and triggers
 * using the CakePHP Migrations plugin.
 */
class ViewsAndTriggersExample extends BaseMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/migrations/4/en/index.html
     */
    public function change(): void
    {
        // Create a users table
        $users = $this->table('users');
        $users->addColumn('username', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 255])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
            ->addColumn('created', 'datetime')
            ->create();

        // Create a posts table
        $posts = $this->table('posts');
        $posts->addColumn('user_id', 'integer')
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('body', 'text')
            ->addColumn('published', 'boolean', ['default' => false])
            ->addColumn('created', 'datetime')
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
            ->create();

        // Create a view showing active users with their post counts
        // Note: Views are created through a dummy table object
        $this->createView(
            'active_users_with_posts',
            'SELECT u.id, u.username, u.email, COUNT(p.id) as post_count
             FROM users u
             LEFT JOIN posts p ON u.id = p.user_id
             WHERE u.status = \'active\'
             GROUP BY u.id, u.username, u.email'
        );

        // Create a materialized view (PostgreSQL only)
        // On other databases, this will create a regular view
        $this->createView(
            'published_posts_summary',
            'SELECT user_id, COUNT(*) as published_count
             FROM posts
             WHERE published = 1
             GROUP BY user_id',
            ['materialized' => true]
        );

        // Create an audit log table for triggers
        $auditLog = $this->table('audit_log');
        $auditLog->addColumn('table_name', 'string', ['limit' => 100])
            ->addColumn('action', 'string', ['limit' => 20])
            ->addColumn('record_id', 'integer')
            ->addColumn('created', 'datetime')
            ->create();

        // Create a trigger to log user insertions
        // Note: The trigger definition syntax varies by database

        // For MySQL:
        $this->createTrigger(
            'users',
            'log_user_insert',
            'INSERT',
            "INSERT INTO audit_log (table_name, action, record_id, created)
             VALUES ('users', 'INSERT', NEW.id, NOW())",
            ['timing' => 'AFTER']
        );

        // For PostgreSQL, you would need to create a function first:
        // $this->execute("
        //     CREATE OR REPLACE FUNCTION log_user_insert_func()
        //     RETURNS TRIGGER AS $$
        //     BEGIN
        //         INSERT INTO audit_log (table_name, action, record_id, created)
        //         VALUES ('users', 'INSERT', NEW.id, NOW());
        //         RETURN NEW;
        //     END;
        //     $$ LANGUAGE plpgsql;
        // ");
        //
        // $this->createTrigger(
        //     'users',
        //     'log_user_insert',
        //     'INSERT',
        //     'log_user_insert_func()',  // Function name for PostgreSQL
        //     ['timing' => 'AFTER']
        // );

        // Create a trigger for updates with multiple events
        $this->createTrigger(
            'posts',
            'log_post_changes',
            ['UPDATE', 'DELETE'],
            "INSERT INTO audit_log (table_name, action, record_id, created)
             VALUES ('posts', 'CHANGE', OLD.id, NOW())",
            ['timing' => 'BEFORE']
        );
    }

    /**
     * Migrate Up.
     *
     * If you need more control, you can use up() and down() methods instead.
     */
    public function up(): void
    {
        // Example of creating a view in up() method
        $this->createView(
            'simple_user_list',
            'SELECT id, username FROM users'
        );
    }

    /**
     * Migrate Down.
     */
    public function down(): void
    {
        // Drop views
        $this->dropView('simple_user_list');
        $this->dropView('active_users_with_posts');
        $this->dropView('published_posts_summary', ['materialized' => true]);

        // Drop triggers
        $this->dropTrigger('users', 'log_user_insert');
        $this->dropTrigger('posts', 'log_post_changes');

        // Drop tables
        $this->table('audit_log')->drop()->save();
        $this->table('posts')->drop()->save();
        $this->table('users')->drop()->save();
    }
}
