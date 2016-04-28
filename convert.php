<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

$console = new Application();
 
$console
    ->register('run')
    ->setDefinition(
        [
            new InputOption('export', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Target image export sizes'),
            new InputOption('layout', null, InputOption::VALUE_REQUIRED, 'Rendering layout for individual photos', 'gallery-photo'),
            new InputArgument('name', null, InputArgument::REQUIRED, 'Gallery name'),
            new InputArgument('assetdir', InputArgument::OPTIONAL, 'Asset directory for exported images', 'asset/gallery'),
            new InputArgument('mdowndir', InputArgument::OPTIONAL, 'Markdown directory for dumping individual photo details', 'gallery'),
        ]
    )
    ->setDescription('Parse a YAML-like gallery configuration and export it.')
    ->setHelp('
The export option will accept values like:
  200x110 - photo will outset the boundary with the dimensions being 200x110
  1280 - photo will inset with the largest dimension being 1280
')
    ->setCode(
        function (InputInterface $input, OutputInterface $output) {
            $gallery = $input->getArgument('name');
            $assetPath = $input->getArgument('assetdir') . '/' . $gallery;
            $renderPath = $input->getArgument('mdowndir') . '/' . $gallery;
            $layout = $input->getOption('layout');
            $exports = $input->getOption('export');

            $stdin = stream_get_contents(STDIN);

            $imagine = new Imagine\Gd\Imagine();

            if (!is_dir($assetPath)) {
                mkdir($assetPath, 0700, true);
            }

            if (!is_dir($renderPath)) {
                mkdir($renderPath, 0700, true);
            }

            $stdinPhotos = explode('------------', trim($stdin));
            $photos = [];

            // load data

            foreach ($stdinPhotos as $i => $photoRaw) {
                $photoSplit = explode('------', trim($photoRaw), 2);

                if (empty($photoSplit[0])) {
                    continue;
                }

                $photo = array_merge(
                    [
                        'ordering' => $i,
                        'comment' => isset($photoSplit[1]) ? $photoSplit[1] : null,
                    ],
                    Yaml::parse($photoSplit[0])
                );

                if (isset($photo['comment']) && ('missing value' == trim($photo['comment']))) {
                  unset($photo['comment']);
                }

                $photo['title'] = preg_replace('/.jpg$/', '', $photo['title']);

                $photo['id'] = substr(sha1_file($photo['path']), 0, 7) . '-' . preg_replace('/(-| )+/', '-', preg_replace('/[^a-z0-9 ]/i', '-', preg_replace('/\'/', '', strtolower(preg_replace('/\p{Mn}/u', '', Normalizer::normalize($photo['title'], Normalizer::FORM_KD))))));

                $photo['date'] = \DateTime::createFromFormat(
                    'l, F j, Y \a\t g:i:s A',
                    $photo['date']
                );

                $photo['exif'] = exif_read_data($photo['path']);

                $photos[] = $photo;
            }

            // manipulate

            foreach ($photos as $i => $photo) {
                $output->write('<info>' . $photo['id'] . '</info>');

                $photo['sizes'] = [];

                // image exports
                if (0 < count($exports)) {
                    $sourceJpg = $imagine->open($photo['path']);

                    if (isset($photo['exif']['Orientation'])) {
                        switch ($photo['exif']['Orientation']) {
                          case 2:
                            $sourceJpg->mirror();
                            
                            break;

                          case 3:
                            $sourceJpg->rotate(180);
                            
                            break;

                          case 4:
                            $sourceJpg->rotate(180)->mirror();
                            
                            break;

                          case 5:
                            $sourceJpg->rotate(90)->mirror();
                            
                            break;

                          case 6:
                            $sourceJpg->rotate(90);
                            
                            break;

                          case 7:
                            $sourceJpg->rotate(-90)->mirror();
                            
                            break;

                          case 8:
                            $sourceJpg->rotate(-90);
                            
                            break;
                        }
                    }

                    $sourceSize = $sourceJpg->getSize();

                    $output->write('[' . $sourceSize->getWidth() . 'x' . $sourceSize->getHeight() . ']...');

                    foreach ($exports as $export) {
                        $output->write('<comment>' . $export . '</comment>');

                        if (false !== strpos($export, 'x')) {
                            list($w, $h) = explode('x', $export);

                            $exportImage = $sourceJpg->thumbnail(
                                new \Imagine\Image\Box($w, $h),
                                \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND
                            );
                        } else {
                            if ('w' == substr($export, -1)) {
                                $mx = (int) $export;
                                $my = ($mx * $sourceSize->getHeight() ) / $sourceSize->getWidth();
                            } elseif ('h' == substr($export, -1)) {
                                $my = (int) $export;
                                $mx = ($my * $sourceSize->getWidth() ) / $sourceSize->getHeight();
                            } elseif ($sourceSize->getWidth() == max($sourceSize->getWidth(), $sourceSize->getHeight())) {
                                $mx = (int) $export;
                                $my = ($mx * $sourceSize->getHeight()) / $sourceSize->getWidth();
                            } elseif ($sourceSize->getHeight() == max($sourceSize->getWidth(), $sourceSize->getHeight())) {
                                $my = (int) $export;
                                $mx = ($my * $sourceSize->getWidth()) / $sourceSize->getHeight();
                            }
                            
                            $exportImage = $sourceJpg->thumbnail(
                                new \Imagine\Image\Box(ceil($mx), ceil($my)),
                                \Imagine\Image\ImageInterface::THUMBNAIL_INSET
                            );
                        }

                        $exportSize = $exportImage->getSize();

                        $photo['sizes'][$export] = [
                            'width' => $exportSize->getWidth(),
                            'height' => $exportSize->getHeight(),
                        ];

                        $output->write('[' . $exportSize->getWidth() . 'x' . $exportSize->getHeight() . ']');

                        $exportPath = $assetPath . '/' . $photo['id'] . '~' . $export . '.jpg';

                        file_put_contents(
                            $exportPath,
                            $exportImage->get('jpeg', [ 'quality' => 90 ])
                        );

                        touch($exportPath, $photo['date']->getTimestamp());

                        $exportImage = null;

                        $output->write('...');
                    }

                    $sourceJpg = null;
                }

                $output->write('<comment>markdown</comment>...');

                $matter = [
                    'layout' => $layout,
                    'title' => $photo['title'],
                    'date' => $photo['date']->format('Y-m-d H:i:s'),
                    'ordering' => $photo['ordering'],
                ];

                if ($photo['exif']) {
                    $matter['exif'] = [
                        'make' => $photo['exif']['Make'],
                        'model' => $photo['exif']['Model'],
                        'aperture' => isset($photo['exif']['COMPUTED']['ApertureFNumber']) ? $photo['exif']['COMPUTED']['ApertureFNumber'] : null,
                        'exposure' => isset($photo['exif']['ExposureTime']) ? $photo['exif']['ExposureTime'] : null,
                    ];
                }

                if (isset($photos[$i - 1])) {
                    $matter['previous'] = '/gallery/' . $gallery . '/' . $photos[$i - 1]['id'];
                }

                if (isset($photos[$i + 1])) {
                    $matter['next'] = '/gallery/' . $gallery . '/' . $photos[$i + 1]['id'];
                }

                if ($photo['latitude']) {
                    $matter['location'] = [
                        'latitude' => $photo['latitude'],
                        'longitude' => $photo['longitude'],
                    ];
                }

                if ($photo['sizes']) {
                    $matter['sizes'] = $photo['sizes'];
                }

                ksort_recursive($matter);

                uasort(
                    $matter['sizes'],
                    function ($a, $b) {
                        $aa = $a['width'] * $a['height'];
                        $bb = $b['width'] * $b['height'];

                        if ($aa == $bb) {
                            return 0;
                        }

                        return ($aa > $bb) ? -1 : 1;
                    }
                );

                file_put_contents(
                    $renderPath . '/' . $photo['id'] . '.md',
                    '---' . "\n" . Yaml::dump($matter, 4, 2) . '---' . "\n" . ((!empty($photo['comment'])) ? ($photo['comment'] . "\n") : '')
                );

                $output->writeln('done');
            }
        }
    )
    ;

$console->run(new ArgvInput(array_merge([ $_SERVER['argv'][0], 'run' ], array_slice($_SERVER['argv'], 1))));

function ksort_recursive (&$array, $sort_flags = SORT_REGULAR) {
    if (!is_array($array)) {
        return false;
    }

    foreach ($array as &$subarray) {
        ksort_recursive($subarray, $sort_flags);
    }

    ksort($array, $sort_flags);

    return true;
}
