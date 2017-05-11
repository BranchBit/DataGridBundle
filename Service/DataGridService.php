<?php
namespace BBIT\DataGridBundle\Service;

use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\TwigBundle\TwigEngine;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;


class DataGridService
{
    private $itemsPerPage;
    /**
     * @var TwigEngine
     */
    private $twig;

    private $fields;
    /**
     * @var QueryBuilder
     */
    private $qb;
    private $pagedData;
    /**
     * @var PaginatorInterface
     */
    private $paginator;
    /**
     * @var RequestStack
     */
    private $requestStack;

    private $accessor;
    
    protected $extraData = [];

    /**
     * @param mixed $extraData
     */
    public function setExtraData($extraData)
    {
        $this->extraData = $extraData;
    }

    /**
     * DataGridService constructor.
     * @param $twig
     * @param PaginatorInterface $paginator
     * @param RequestStack $request
     */
    public function __construct(TwigEngine $twig, PaginatorInterface $paginator, RequestStack $request)
    {
        $this->twig = $twig;
        $this->fields = [];
        $this->paginator = $paginator;
        $this->requestStack = $request;
        $this->accessor = PropertyAccess::createPropertyAccessor();

    }

    public function addField($fieldName, $type = 'string', $options = [])
    {
        $this->fields[] = ['fieldName' => $fieldName, 'type' => $type, 'options' => $options];
    }

    public function setQb($qb)
    {
        $this->qb = $qb;
    }

    /**
     * @param mixed $itemsPerPage
     */
    public function setItemsPerPage($itemsPerPage)
    {
        $this->itemsPerPage = $itemsPerPage;
    }

    public function addFiltersToQb()
    {
        foreach ($this->fields as $field) {
            if (isset($field['options'], $field['options']['filterable']) && $field['options']['filterable']) {
                $filterParam = $this->requestStack->getCurrentRequest()->query->get('filter');
                $this->qb->andWhere('x.'.$field['fieldName'].' LIKE :'.$field['fieldName'])->setParameter($field['fieldName'], '%'.$filterParam[$field['fieldName']].'%');
            }
        }

        $this->pagedData = $this->paginator->paginate(
            $this->qb->getQuery(), /* query NOT result */
            $this->requestStack->getCurrentRequest()->query->getInt('page', 1)/*page number*/,
            $this->itemsPerPage/*limit per page*/
        );

    }

    public function doCallbacks()
    {
        $accessor = PropertyAccess::createPropertyAccessor();
        foreach ($this->fields as $field) {
            if (isset($field['options'], $field['options']['callback'])) {
                foreach ($this->pagedData->getItems() as $item) {
                    $oldValue = $accessor->getValue($item, $field['fieldName']);
                    //use item instead of value for callback
                    $callback = $field['options']['callback'];
                    $extraCallbackData = [];
                    if (isset($field['options']['extra_callback_data'])) {
                        $extraCallbackData = $field['options']['extra_callback_data'];
                    }
                    if ($field['type'] == 'custom_callback') {
                        $item->{$field['fieldName']} = $callback($item, $extraCallbackData);
//                        /$accessor->setValue($item, $field['fieldName'], $callback($item));
                    } else {
                        $accessor->setValue($item, $field['fieldName'], $callback($oldValue, $extraCallbackData));
                    }

                }
            } else{

            }
        }
    }

    public function render()
    {
        $this->addFiltersToQb();
        $this->doCallbacks();
        return $this->twig->render('BBITDataGridBundle:Default:grid.html.twig', array_merge([
            'accessor' => $this->accessor,
            'fields' => $this->fields,
            'data' => $this->pagedData
            ], $this->extraData)

        );
    }
}
