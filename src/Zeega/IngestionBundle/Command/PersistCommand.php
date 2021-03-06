<?php

/*
* This file is part of Zeega.
*
* (c) Zeega <info@zeega.org>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Zeega\IngestionBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

use Zeega\CoreBundle\Helpers\ResponseHelper;
use Zeega\DataBundle\Entity\Item;

/**
 * Saves an item or a set of items on the database.
 *
 */
class PersistCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */    
    protected function configure()
    {
        $this->setName('zeega:persist')
             ->setDescription('Persist a dataset in Zeega format')
             ->addOption('file_path', null, InputOption::VALUE_REQUIRED, 'Url of the item or collection to be ingested')
             ->addOption('user', null, InputOption::VALUE_REQUIRED, 'Url of the item or collection to be ingested')
             ->addOption('ingestor', null, InputOption::VALUE_REQUIRED, 'Url of the item or collection to be ingested')
             ->addOption('check_for_duplicates', null, InputOption::VALUE_NONE,'If set, items that already exist on the database will not
             be imported')
             ->addOption('replace_duplicates', null, InputOption::VALUE_NONE, 'If set, items (checked by URIs) that already exist on the database will be replaced with any updated information')
             ->setHelp("Help");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filePath = $input->getOption('file_path');
        $userId = $input->getOption('user');
        $ingestor = $input->getOption('ingestor');
        $duplicateCheck = $input->getOption('check_for_duplicates');
        $replaceDuplicates = $input->getOption('replace_duplicates');

        if(null === $filePath || null === $userId || null === $ingestor) {
            $output->writeln('<info>Please run the operation with the --file_path, --ingestor and --user options to execute</info>');
        } else {
            $em = $this->getContainer()->get('doctrine')->getEntityManager();
            $user = $em->getRepository('ZeegaDataBundle:User')->findOneById($userId);

            if( !isset($user) ) {
                $output->writeln("<error>Aborting</error>");
                $output->writeln("<error>The user provided does not exist</error>");
                return;
            }

            $item = json_decode(file_get_contents($filePath),true);
            $items = $item["items"]; // hammer
            if( !isset($item["items"]) ) {
                $output->writeln("<error>Aborting</error>");
                $output->writeln("<error>The file provided doesn't seem to have an items array. Wrong format?</error>");
                return;
            }

            $itemService = $this->getContainer()->get('zeega.item');
            $count = 0;
			$replace_count = 0;
			$duplicate_count = 0;

            foreach($items as $item) {
                if( $duplicateCheck ) {
                    $dbItem = $em->getRepository('ZeegaDataBundle:Item')->findOneBy(array("uri"=>$item["uri"], "user"=>$user));
                    if( isset ($dbItem) ) {
						$duplicate_count++;
                        continue;
                    }
                } 
                if ($replaceDuplicates) {
                    $dbItem = $em->getRepository('ZeegaDataBundle:Item')->findOneBy(array("attribution_uri"=>$item["attribution_uri"], "user"=>$user));
                    if ( isset ($dbItem) ) {
                        $tags = $item["tags"];
                        $dbItem->setTags($tags);
                        $lat = ( isset($item["media_geo_latitude"]) ? $item["media_geo_latitude"] : null);
                        $dbItem->setMediaGeoLatitude($lat);
                        $long = ( isset($item["media_geo_longitude"]) ? $item["media_geo_longitude"] : null) ;
                        $dbItem->setMediaGeoLongitude($long);
                        $location = ( isset($item["location"]) ? $item["location"] : null);
                        $dbItem-> setLocation($location);
                        $title = $item["title"];
                        $dbItem->setTitle($title);
			$date = $item["media_date_created"];
			$datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $date);
			$dbItem->setMediaDateCreated($datetime);
			
			$media = $item["media_type"];
			$dbItem->setMediaType($media);
			$layer = $item["layer_type"];
			$dbItem->setLayerType($layer);
			$uri = $item["uri"];
			$dbItem->setUri($uri);
                        
                        $replace_count++;
                        $em->persist($dbItem);
                        if ($replace_count % 100 == 0) {
                          $em->flush();
                        }
                        continue;
                    }
                }
                $count++;
                $item = $itemService->parseItem($item, $user);
                $em->persist($item);

                if ($count % 100 == 0) {
                    $em->flush();
                }
            }
            
            $em->flush();

            $output->writeln("<info>Ingestion complete. $count items added to the database. $replace_count items updated. $duplicate_count duplicates found.</info>");
        }
    } 
}
