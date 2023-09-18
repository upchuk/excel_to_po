<?php

use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Translation;
use Gettext\Translations;
use Shuchkin\SimpleXLSX;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

require 'vendor/autoload.php';

(new SingleCommandApplication())
  ->setName('Process an Excel to PO')
  ->addArgument('file', InputArgument::REQUIRED, 'The file')
  ->addOption('rows', null, InputOption::VALUE_OPTIONAL, 'The number of rows to export', 1000000)
  ->setCode(function (InputInterface $input, OutputInterface $output): int {

    $file = $input->getArgument('file');
    $total_rows = $input->getOption('rows');
    if (!file_exists($file)) {
      $output->writeln('<error>The file does not exist</error>');
      return Command::INVALID;
    }

    $xlsx = SimpleXLSX::parse($file);
    $originals = [];
    foreach ($xlsx->rowsEx() as $row_index => $row) {
      if ($row_index === 0) {
        continue;
      }

      foreach ($row as $col_index => $col_info) {
        if ($col_index === 1) {
          $originals[$row_index] = $col_info['value'];
        }
      }
    }

    $translations = [];
    $language_column_numbers = [];
    foreach ($xlsx->rowsEx() as $row_index => $row) {
      if ($row_index == ((int) $total_rows + 1)) {
        break;
      }
      if ($row_index === 0) {
        foreach ($row as $col_index => $col_info) {
          if (in_array($col_index, [0, 1])) {
            continue;
          }

          $col_letter = preg_replace("/[^A-Z]+/", "", $col_info['name']);
          $language_column_numbers[$col_letter] = $col_info['value'];
        }
        continue;
      }

      foreach ($row as $col_index => $col_info) {
        if (in_array($col_index, [0, 1])) {
          continue;
        }

        $col_letter = preg_replace("/[^A-Z]+/", "", $col_info['name']);
        $langcode = $language_column_numbers[$col_letter];
        $translations[$row_index][$langcode] = $col_info['value'];
      }
    }

    $translations_objects = [];
    foreach ($translations as $row_index => $row_translations) {
      foreach ($row_translations as $langcode => $translation_value) {
        if (!isset($translations_objects[$langcode])) {
          $translations_objects[$langcode] = Translations::create('excel_to_po', $langcode);
        }
        $translations_object = $translations_objects[$langcode];
        $translation = Translation::create('', $originals[$row_index]);
        $translation->translate($translation_value);
        $translations_object->add($translation);
      }
    }

    $generator = new PoGenerator();
    $loader = new PoLoader();
    foreach ($translations_objects as $object) {
      $outputPath = __DIR__ . '/output/' . $object->getLanguage() . '.po';

      // Check if the file already exists
      if (file_exists($outputPath)) {
        // Load existing translations
        $existingTranslations = $loader->loadFile($outputPath);
        foreach ($object as $translation) {
          // If the existing translations don't already have this translation, add it.
          if (!$existingTranslations->find($translation->getContext(), $translation->getOriginal())) {
            $existingTranslations->add($translation);
          }
        }
        // Generate the updated .po file with merged translations.
        $generator->generateFile($existingTranslations, $outputPath);
      } else {
        // If the file doesn't exist, simply generate it.
        $generator->generateFile($object, $outputPath);
      }
      // Post-process the file in order to remove Gettext headers.
      $content = file_get_contents($outputPath);
      $content = preg_replace('/"Language:.*?"\n/', '', $content);
      $content = preg_replace('/"Plural-Forms:.*?"\n/', '', $content);
      $content = preg_replace('/"X-Domain:.*?"\n/', '', $content);
      file_put_contents($outputPath, $content);
    }

    return Command::SUCCESS;

  })
  ->run();
