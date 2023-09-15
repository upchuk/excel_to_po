<?php

use Gettext\Generator\PoGenerator;
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
  ->setName('Process and Excel to PO')
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
    foreach ($translations_objects as $object) {
      $generator->generateFile($object, __DIR__ . '/output/' . $object->getLanguage() . '.po');
    }

    return Command::SUCCESS;

  })
  ->run();
