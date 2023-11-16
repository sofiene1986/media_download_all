<?php

namespace Drupal\Tests\media_download_all\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * @coversDefaultClass \Drupal\file\Plugin\Field\FieldFormatter\FileAudioFormatter
 * @group file
 */
class MdaFormatterTest extends BrowserTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The field name in media.
   *
   * @var string
   */
  protected $fieldMameInMedia;

  /**
   * The field name in entity test.
   *
   * @var string
   */
  protected $fieldNameInEntityTest;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'media_download_all',
    'entity_test',
    'field',
    'file',
    'user',
    'system',
    'media_test_type',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $entity_type = 'media';
    $bundle = 'test';
    $field_name = mb_strtolower($this->randomMachineName());

    // Add a file field to media:test.
    FieldStorageConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'type' => 'file',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ])->save();
    FieldConfig::create([
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      'bundle' => $bundle,
      'settings' => [
        'file_extensions' => trim('txt'),
      ],
    ])->save();

    $this->fieldMameInMedia = $field_name;

    // Add a entity reference field to entity_test.
    $field_name2 = mb_strtolower($this->randomMachineName());

    FieldStorageConfig::create([
      'field_name' => $field_name2,
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'media',
      ],
    ])->save();

    FieldConfig::create([
      'field_name' => $field_name2,
      'entity_type' => 'entity_test',
      'bundle' => 'entity_test',
    ])->save();

    $this->fieldNameInEntityTest = $field_name2;

    $user = $this->createUser([
      'administer media',
      'administer entity_test content',
      'view test entity',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests formatter with non-empty media filed.
   *
   * @dataProvider mdaFormatters
   */
  public function testFormatterWithNonemptyMedia($formatter) {

    $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent($this->fieldNameInEntityTest, [
        'type' => $formatter,
      ])->save();

    file_put_contents('public://file.txt', str_repeat('t', 10));
    file_put_contents('public://file2.txt', str_repeat('t', 10));

    $file1 = File::create([
      'uri' => 'public://file.txt',
      'filename' => 'file.txt',
    ]);
    $file1->save();

    $file2 = File::create([
      'uri' => 'public://file2.txt',
      'filename' => 'file2.txt',
    ]);
    $file2->save();

    $media1 = Media::create([
      $this->fieldMameInMedia => [
        'target_id' => $file1->id(),
      ],
      'bundle' => 'test',
    ]);
    $media1->save();

    $media2 = Media::create([
      $this->fieldMameInMedia => [
        'target_id' => $file2->id(),
      ],
      'bundle' => 'test',
    ]);
    $media2->save();

    $entity = EntityTest::create([
      'type' => 'entity_test',
      $this->fieldNameInEntityTest => [
        [
          'target_id' => $media1->id(),
        ],
        [
          'target_id' => $media2->id(),
        ],
      ],
    ]);
    $entity->save();

    $entity_id = $entity->id();

    $this->drupalGet($entity->toUrl());
    $this->assertResponse(200);
    $link = $this->xpath("//a[@class='media-download-all']");
    $this->assertCount(1, $link);
    $mda_link = $link[0];
    $this->assertEquals('Download All Files', $mda_link->getText());
    $this->assertStringEndsWith("/media_download_all/entity_test/$entity_id/" . $this->fieldNameInEntityTest, $mda_link->getAttribute('href'));
  }

  /**
   * Tests formatter with empty media filed.
   *
   * @dataProvider mdaFormatters
   */
  public function testFormatterWithEmptyMedia($formatter) {

    $this->container->get('entity_display.repository')
      ->getViewDisplay('entity_test', 'entity_test', 'full')
      ->setComponent($this->fieldNameInEntityTest, [
        'type' => $formatter,
      ])->save();

    $entity = EntityTest::create([
      'type' => 'entity_test',
    ]);
    $entity->save();
    $this->drupalGet($entity->toUrl());
    $this->assertResponse(200);
    $link = $this->xpath("//a[@class='media-download-all']");
    $this->assertCount(0, $link);
  }

  /**
   * Date provider.
   *
   * @return array
   *   The formatters.
   */
  public function mdaFormatters() {
    return [
      ['media_download_all_entity_view'],
      ['media_download_all_entity_id'],
      ['media_download_all_label'],
      ['media_download_all_thumbnail'],
    ];
  }

}
