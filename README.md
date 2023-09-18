# Excel to PO

This is a simple console command that you can run to generate .po files from an Excel.

## Installation

Clone the repo locally and you are good to go.

## Excel format

The excel needs to be in the following format:

| Context                     | en             | fr             | bg             | etc |
|-----------------------------|----------------|----------------|----------------|-----|
| Some description (optional) | English string | FR translation | BG translation | ... |

## Command

```
php index.php [file] [--rows=INT] [--prefix=STRING]
```

For example, you can add the Excel file into the a `source` folder of the repo and run the command:

```
php index.php source/excel.xlsx
```

Optionally, you can specify how many rows from the Excel to parse and a string as prefix for the .po file name

```
php index.php source/excel.xlsx --rows=5 --prefix=my_module-
```

The results are dumped into the `output` folder (which is ignored by git) as individual .po files. If .po files exists
in the path, the existing .po files will be appended with the new translations.
