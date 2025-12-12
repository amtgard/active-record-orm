<?php

namespace Tests\Unit\Trait;

use Amtgard\ActiveRecordOrm\Attribute\EntityOf;
use Amtgard\ActiveRecordOrm\Attribute\Field;
use Amtgard\ActiveRecordOrm\Attribute\PrimaryKey;
use Amtgard\ActiveRecordOrm\Entity\Entity;
use Amtgard\ActiveRecordOrm\Entity\EntityMapper;
use Amtgard\ActiveRecordOrm\Entity\Repository\Repository;
use Amtgard\ActiveRecordOrm\Entity\Repository\RepositoryEntity;
use Amtgard\ActiveRecordOrm\EntityManager;
use Amtgard\ActiveRecordOrm\Interface\EntityInterface;
use Amtgard\ActiveRecordOrm\Interface\EntityRepositoryInterface;
use Amtgard\ActiveRecordOrm\Repository\Database;
use Amtgard\ActiveRecordOrm\Schema\FieldDefinition;
use Amtgard\ActiveRecordOrm\Schema\FieldType;
use Amtgard\ActiveRecordOrm\Schema\TableSchema;
use Amtgard\ActiveRecordOrm\Table;
use Amtgard\ActiveRecordOrm\Trait\RepositoryEntityTrait;
use Amtgard\PHPUnit\AmtgardTestCase;
use Amtgard\Traits\Builder\Builder;
use Amtgard\Traits\Builder\Data;
use Phake;
use function PHPUnit\Framework\assertEquals;

class TestRepositoryForEntity extends Repository
{
    public static function getTableName()
    {
        return 'test_table';
    }

    public static function getEntityClass()
    {
        return TestRepositoryEntityForTrait::class;
    }
}

#[EntityOf(TestRepositoryForEntity::class)]
class TestRepositoryEntityForTrait extends RepositoryEntity
{
    use Builder, Data, RepositoryEntityTrait;

    #[PrimaryKey]
    private ?int $id;

    #[Field('name_field')]
    private ?string $name;

    #[Field('created_at')]
    private ?\DateTime $createdAt;
}

#[EntityOf(TestRepositoryForEntity::class)]
class TestRepositoryEntityWithBackingRef extends RepositoryEntity
{
    use Builder, Data, RepositoryEntityTrait;

    #[PrimaryKey]
    private ?int $id;

    #[Field('name_field')]
    private ?string $name;

    private ?int $linkId;

    #[Field('int_value', 'linkId')]
    private ?TestRepositoryEntityForTrait $link;
}

class RepositoryEntityTraitTest extends AmtgardTestCase
{
    private EntityMapper $mockEntityMapper;
    private EntityManager $mockEntityManager;
    private Entity $mockEntity;
    private TableSchema $mockTableSchema;
    private FieldDefinition $mockPrimaryKey;
    private Database $mockDatabase;
    private Table $mockTable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockEntityMapper = Phake::mock(EntityMapper::class);
        $this->mockEntityManager = Phake::mock(EntityManager::class);
        $this->mockEntity = Phake::mock(Entity::class);
        $this->mockTableSchema = Phake::mock(TableSchema::class);
        $this->mockPrimaryKey = Phake::mock(FieldDefinition::class);
        $this->mockDatabase = Phake::mock(Database::class);
        $this->mockTable = Phake::mock(Table::class);

        Phake::when($this->mockEntityMapper)->getName()->thenReturn('test_table');
        Phake::when($this->mockEntity)->getPrimaryKey()->thenReturn($this->mockPrimaryKey);
        Phake::when($this->mockPrimaryKey)->getValue()->thenReturn(1);
        Phake::when($this->mockEntity)->isDirty()->thenReturn(true);
        Phake::when($this->mockEntity)->getChanges()->thenReturn(['name_field' => 'new_value']);
        Phake::when($this->mockEntity)->schema->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntityMapper)->getTable()->thenReturn($this->mockTable);
        Phake::when($this->mockTable)->getName()->thenReturn('test_table');

        // Mock field definitions for the schema fields used in TestRepositoryEntityForTrait
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);
        
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField,
        ]);

        EntityManager::configure($this->mockEntityManager);

        Phake::when($this->mockEntityManager)->getMapper('test_table')->thenReturn($this->mockEntityMapper);
        Phake::when($this->mockEntityManager)->getRepository(TestRepositoryForEntity::class)->thenReturn(Phake::mock(TestRepositoryForEntity::class));
    }

    protected function tearDown(): void
    {
        // Clean up static state after each test
        $this->resetEntityManagerStaticState();
        parent::tearDown();
    }

    private function resetEntityManagerStaticState(): void
    {
        // Use reflection to reset the static instance
        $reflection = new \ReflectionClass(EntityManager::class);
        $instanceProperty = $reflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        $instanceProperty->setValue(null, null);
    }

    public function testIsDirty_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->isDirty();

        self::assertTrue($result);
        Phake::verify($this->mockEntity)->isDirty();
    }

    public function testGetChanges_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getChanges();

        assertEquals(['name_field' => 'new_value'], $result);
        Phake::verify($this->mockEntity)->getChanges();
    }

    public function testGetPrimaryKey_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getPrimaryKey();

        self::assertSame($this->mockPrimaryKey, $result);
        Phake::verify($this->mockEntity)->getPrimaryKey();
    }

    public function testPersist_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->persist($this->mockEntityMapper);

        Phake::verify($this->mockEntity)->persist($this->mockEntityMapper);
    }

    public function testGetSchema_delegatesToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getSchema();

        self::assertSame($this->mockTableSchema, $result);
    }

    public function testGetInternalEntity_returnsEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = $entity->getInternalEntity();

        self::assertSame($this->mockEntity, $result);
    }

    public function testSetInternalEntity_setsEntity(): void
    {
        $newEntity = Phake::mock(Entity::class);
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->setInternalEntity($newEntity);

        self::assertSame($newEntity, $entity->getInternalEntity());
    }

    public function testToRepositoryEntity_convertsEntity(): void
    {
        // Setup all fields that are in the entity map info
        // The entity map info has: id (source: 'id'), name (source: 'name_field'), createdAt (source: 'created_at')
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        
        // Use thenReturn with a fresh array each time to avoid reference issues
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
    }

    public function testToRepositoryEntity_withRepositoryEntityTrait_usesInternalEntity(): void
    {
        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        // Create an entity that already uses RepositoryEntityTrait
        $entityWithTrait = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($entityWithTrait);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
    }

    public function testPreInit_initializesMapper(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->build();

        $reflection = new \ReflectionClass($entity);
        $mapperProperty = $reflection->getProperty('mapper');
        $mapperProperty->setAccessible(true);

        self::assertInstanceOf(EntityMapper::class, $mapperProperty->getValue($entity));
    }

    public function testPostInit_buildsEntityMapInfo(): void
    {
        $mockRepository = Phake::mock(TestRepositoryForEntity::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepositoryForEntity::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        $entity = TestRepositoryEntityForTrait::builder()
            ->build();

        $reflection = new \ReflectionClass($entity);
        $entityMapInfoProperty = $reflection->getProperty('entityMapInfo');
        $entityMapInfoProperty->setAccessible(true);
        $entityMapInfo = $entityMapInfoProperty->getValue($entity);

        self::assertIsArray($entityMapInfo);
        self::assertArrayHasKey('id', $entityMapInfo);
        self::assertArrayHasKey('name', $entityMapInfo);
    }

    public function testOnSetField_mapsToEntity(): void
    {
        $entity = TestRepositoryEntityForTrait::builder()
            ->entity($this->mockEntity)
            ->build();

        $entity->name = 'test_name';

        Phake::verify($this->mockEntity)->__set('name_field', 'test_name');
    }

    public function testPostInit_buildsEmptyEntityWhenNotSet(): void
    {
        $mockRepository = Phake::mock(TestRepositoryForEntity::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepositoryForEntity::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        $entity = TestRepositoryEntityForTrait::builder()->build();

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $entity);
        Phake::verify($mockRepository)->createMapperEntity();
    }

    public function testMapFieldsToInternalEntity_mapsFieldValuesToEntity(): void
    {
        $mockRepository = Phake::mock(TestRepositoryForEntity::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepositoryForEntity::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        // Set field values before building - these should be mapped to the internal entity
        $entity = TestRepositoryEntityForTrait::builder()
            ->id(42)
            ->name('test_mapped_name')
            ->build();

        // Verify that the internal entity received the mapped values
        // The 'id' field maps to 'id' in the entity (PrimaryKey uses property name as default)
        // The 'name' field maps to 'name_field' in the entity (Field('name_field'))
        Phake::verify($this->mockEntity)->__set('id', 42);
        Phake::verify($this->mockEntity)->__set('name_field', 'test_mapped_name');
    }

    public function testMapFieldsToInternalEntity_onlyMapsSetFields(): void
    {
        $mockRepository = Phake::mock(TestRepositoryForEntity::class);
        Phake::when($this->mockEntityManager)->getRepository(TestRepositoryForEntity::class)->thenReturn($mockRepository);
        Phake::when($mockRepository)->createMapperEntity()->thenReturn($this->mockEntity);

        // Only set one field - mapFieldsToInternalEntity should only map fields that are set
        $entity = TestRepositoryEntityForTrait::builder()
            ->name('only_this_field')
            ->build();

        // Verify that the set field was mapped to the internal entity using the source field name
        // Line 167: $entityFieldName = $mapInfo['source'];
        // Line 168: $this->entity->$entityFieldName = $this->$fieldName;
        Phake::verify($this->mockEntity)->__set('name_field', 'only_this_field');
    }

    public function testToRepositoryEntity_convertsDateTimeFromDatetime(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::DATETIME);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('2023-01-01 12:00:00');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
            self::assertEquals('2023-01-01 12:00:00', $createdAtValue->format('Y-m-d H:i:s'));
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_convertsDateTimeFromInteger(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('1672574400');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_convertsDateTimeDefaultCase(): void
    {
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        // Use a type that's neither DATETIME nor INTEGER to trigger default case
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->created_at->thenReturn('some_value');
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
        $reflection = new \ReflectionClass($result);
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        // Check if property is initialized
        if ($createdAtProperty->isInitialized($result)) {
            $createdAtValue = $createdAtProperty->getValue($result);
            // Default case creates a new DateTime() with current time
            self::assertInstanceOf(\DateTime::class, $createdAtValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('createdAt property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_handlesDefaultFieldType(): void
    {
        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test_name');
        Phake::when($this->mockEntity)->created_at->thenReturn(null);

        $result = TestRepositoryEntityForTrait::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
    }

    public function testToRepositoryEntity_withBackingReferencePk_setsBackingField(): void
    {
        $mockIntValueField = Phake::mock(FieldDefinition::class);
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $fieldsArray = [
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'int_value' => $mockIntValueField
        ];
        Phake::when($this->mockTableSchema)->getFields()->thenReturn($fieldsArray);
        Phake::when($mockIntValueField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($this->mockEntity)->getSchema()->thenReturn($this->mockTableSchema);
        Phake::when($this->mockEntity)->int_value->thenReturn(42);
        Phake::when($this->mockEntity)->id->thenReturn(1);
        Phake::when($this->mockEntity)->name_field->thenReturn('test');

        $result = TestRepositoryEntityWithBackingRef::toRepositoryEntity($this->mockEntity);

        self::assertInstanceOf(TestRepositoryEntityWithBackingRef::class, $result);
        $reflection = new \ReflectionClass($result);
        $linkIdProperty = $reflection->getProperty('linkId');
        $linkIdProperty->setAccessible(true);
        // Check if property is initialized
        if ($linkIdProperty->isInitialized($result)) {
            $linkIdValue = $linkIdProperty->getValue($result);
            // When backingReferencePk is set, it should set the backing field instead of the entity field
            self::assertEquals(42, $linkIdValue);
        } else {
            // Property should be set by fieldTypeConversions
            self::fail('linkId property should be initialized after conversion');
        }
    }

    public function testToRepositoryEntity_withEntityInterfaceWithoutBackingRef_assignsDirectly(): void
    {
        // Create a mock entity that implements EntityInterface but doesn't have backingReferencePk
        $mockEntityInterface = Phake::mock(Entity::class);
        Phake::when($mockEntityInterface)->getSchema()->thenReturn($this->mockTableSchema);
        
        // Setup all fields that are in the entity map info
        $mockIdField = Phake::mock(FieldDefinition::class);
        $mockNameField = Phake::mock(FieldDefinition::class);
        $mockCreatedAtField = Phake::mock(FieldDefinition::class);
        Phake::when($this->mockTableSchema)->getFields()->thenReturn([
            'id' => $mockIdField,
            'name_field' => $mockNameField,
            'created_at' => $mockCreatedAtField
        ]);
        Phake::when($mockIdField)->getType()->thenReturn(FieldType::INTEGER);
        Phake::when($mockNameField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockCreatedAtField)->getType()->thenReturn(FieldType::STRING);
        Phake::when($mockEntityInterface)->id->thenReturn(1);
        Phake::when($mockEntityInterface)->name_field->thenReturn('test_value');
        Phake::when($mockEntityInterface)->created_at->thenReturn(null);

        // This should fall through to the default assignment case
        $result = TestRepositoryEntityForTrait::toRepositoryEntity($mockEntityInterface);

        self::assertInstanceOf(TestRepositoryEntityForTrait::class, $result);
    }
}
