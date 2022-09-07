<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\BitrixTokensTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\BitrixTokensTable Test Case
 */
class BitrixTokensTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\BitrixTokensTable
     */
    protected $BitrixTokens;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.BitrixTokens',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('BitrixTokens') ? [] : ['className' => BitrixTokensTable::class];
        $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->BitrixTokens);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\BitrixTokensTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
