<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\LangCodesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\LangCodesTable Test Case
 */
class LangCodesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\LangCodesTable
     */
    protected $LangCodes;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.LangCodes',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('LangCodes') ? [] : ['className' => LangCodesTable::class];
        $this->LangCodes = $this->getTableLocator()->get('LangCodes', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->LangCodes);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\LangCodesTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
