<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\ResolutionsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\ResolutionsTable Test Case
 */
class ResolutionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\ResolutionsTable
     */
    protected $Resolutions;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Resolutions',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Resolutions') ? [] : ['className' => ResolutionsTable::class];
        $this->Resolutions = $this->getTableLocator()->get('Resolutions', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Resolutions);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\ResolutionsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
