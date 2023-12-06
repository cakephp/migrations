<?php
declare(strict_types=1);

/**
 * MIT License
 * For full license information, please view the LICENSE file that was distributed with this source code.
 */

namespace Migrations\Db;

// TODO replace/merge with cakephp/database
class Literal
{
    /**
     * @var string The literal's value
     */
    protected string $value;

    /**
     * @param string $value The literal's value
     */
    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @return string Returns the literal's value
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * @param string $value The literal's value
     * @return self
     */
    public static function from(string $value): Literal
    {
        return new self($value);
    }
}
