<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShopBundle\Form\Admin\Sell\Product\EventListener;

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;

/**
 * This listener dynamically updates the form depending on the product type, like
 * is it virtual, a pack, does it have combinations, ...
 */
class ProductTypeListener implements EventSubscriberInterface
{
    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'adaptProductForm',
            FormEvents::PRE_SUBMIT => 'adaptProductForm',
        ];
    }

    /**
     * @param FormEvent $event
     */
    public function adaptProductForm(FormEvent $event): void
    {
        $data = $event->getData();
        $form = $event->getForm();
        // We need both initial and new types because we may need to keep some fields during the transition request
        $productType = $data['header']['type'];
        $initialProductType = $data['header']['initial_type'] ?? $productType;

        if (ProductType::TYPE_COMBINATIONS === $productType) {
            $this->removeSuppliers($form, $data);
            $this->removeStock($form, $data);
        } else {
            $this->removeCombinations($form, $data);
        }

        if (ProductType::TYPE_PACK !== $productType) {
            $this->removePackStockType($form, $data);
            $this->removePack($form, $data);
        }

        $this->removeStockMovementsIfNecessary($form, $data);

        if (ProductType::TYPE_VIRTUAL === $productType) {
            $this->removeShipping($form, $data);
            // We don't remove the ecotax during the transition request because we could lose the ecotax data
            // and some part of the price with it
            if (ProductType::TYPE_VIRTUAL === $initialProductType) {
                $this->removeEcotax($form, $data);
            }
        } else {
            $this->removeVirtualProduct($form, $data);
        }

        $event->setData($data);
    }

    protected function removeCombinations(FormInterface $form, array &$data): void
    {
        if ($form->has('options')) {
            $form->remove('combinations');
        }
    }

    protected function removeSuppliers(FormInterface $form, array &$data): void
    {
        if ($form->has('options')) {
            $optionsForm = $form->get('options');
            $optionsForm->remove('product_suppliers');
        }
    }

    protected function removeStock(FormInterface $form, array &$data): void
    {
        $form->remove('stock');
    }

    protected function removePackStockType(FormInterface $form, array &$data): void
    {
        if (!$form->has('stock')) {
            return;
        }
        $stock = $form->get('stock');
        $stock->remove('pack_stock_type');
    }

    protected function removePack(FormInterface $form, array &$data): void
    {
        if (!$form->has('stock')) {
            return;
        }
        $stock = $form->get('stock');
        $stock->remove('packed_products');
    }

    protected function removeVirtualProduct(FormInterface $form, array &$data): void
    {
        if (!$form->has('stock')) {
            return;
        }
        $stock = $form->get('stock');
        $stock->remove('virtual_product_file');
    }

    protected function removeStockMovementsIfNecessary(FormInterface $form, array &$data): void
    {
        if (!$form->has('stock')) {
            return;
        }
        $stock = $form->get('stock');
        if (!$stock->has('quantities')) {
            return;
        }
        $quantities = $stock->get('quantities');
        if ($quantities->has('stock_movements') && empty($data['stock']['quantities']['stock_movements'])) {
            $quantities->remove('stock_movements');
        }
    }

    protected function removeShipping(FormInterface $form, array &$data): void
    {
        $form->remove('shipping');
    }

    protected function removeEcotax(FormInterface $form, array &$data): void
    {
        if (!$form->has('pricing')) {
            return;
        }
        $pricing = $form->get('pricing');
        if (!$pricing->has('retail_price')) {
            return;
        }
        $retailPrice = $pricing->get('retail_price');
        if ($retailPrice->has('ecotax_tax_excluded')) {
            $retailPrice->remove('ecotax_tax_excluded');
        }

        if ($retailPrice->has('ecotax_tax_included')) {
            $retailPrice->remove('ecotax_tax_included');
        }
    }
}
