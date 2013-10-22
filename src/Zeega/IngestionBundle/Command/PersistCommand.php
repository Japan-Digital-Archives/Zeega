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
        $replaceDuplicates = $input->getOption('replace_duplcates');

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

            foreach($items as $item) {
                if( $duplicateCheck ) {
                    $dbItem = $em->getRepository('ZeegaDataBundle:Item')->findOneBy(array("uri"=>$item["uri"], "user"=>$user));
                    if( isset ($dbItem) ) {
                        continue;
                    }
                } 
                if ($replaceDuplicates) {
                    $dbItem = $em->getRepository('ZeegaDataBundle:Item')->findOneBy(array("uri"=>$item["uri"], "user"=>$user));
                    if ( isset ($dbItem) ) {
                        $tags = $item->getTags();
                        $dbItem->setTags($tags);
                        $lat = $item->getMediaGeoLatitude();
                        $dbItem->setMediaGeoLatitude($lat);
                        $long = $item->getMediaGeoLongitude();
                        $dbItem->setMediaGeoLongitude($long);
                        $location = $item->getLocation();
                        $dbItem->setLocation($location);
                        $title = $item->getTitle();
                        $dbItem->setTitle($title);
                        $description = $item->getDescription();
                        $dbItem->setDescription($description);
                        
                        $count++;
                        $em->persist($item);
                        if ($count % 100 == 0) {
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

            $output->writeln("<info>Ingestion complete. $count items added to the database.</info>");
        }
    } 
}
