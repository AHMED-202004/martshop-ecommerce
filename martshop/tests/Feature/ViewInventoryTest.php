<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ViewInventoryTest extends TestCase
{
    public function test_every_mutating_blade_form_contains_a_csrf_token(): void
    {
        $checked = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();
            preg_match_all('/<form\b[^>]*\bmethod\s*=\s*["\']post["\'][^>]*>[\s\S]*?<\/form>/i', $contents, $forms);

            foreach ($forms[0] as $form) {
                $checked++;
                $this->assertStringContainsString('@csrf', $form, $file->getPathname());
            }
        }

        $this->assertGreaterThan(0, $checked);
    }

    public function test_upload_forms_and_password_fields_have_safe_browser_attributes(): void
    {
        $uploads = 0;
        $passwords = 0;

        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();
            preg_match_all('/<form\b[^>]*>[\s\S]*?<\/form>/i', $contents, $forms);
            foreach ($forms[0] as $form) {
                if (preg_match('/<input\b[^>]*\btype\s*=\s*["\']file["\']/i', $form)) {
                    $uploads++;
                    $this->assertMatchesRegularExpression(
                        '/<form\b[^>]*\benctype\s*=\s*["\']multipart\/form-data["\']/i',
                        $form,
                        $file->getPathname(),
                    );
                }
            }

            preg_match_all('/<input\b[^>]*\btype\s*=\s*["\']password["\'][^>]*>/i', $contents, $fields);
            foreach ($fields[0] as $field) {
                $passwords++;
                $this->assertMatchesRegularExpression(
                    '/\bautocomplete\s*=\s*["\'](?:current-password|new-password)["\']/i',
                    $field,
                    $file->getPathname(),
                );
            }
        }

        $this->assertGreaterThan(0, $uploads);
        $this->assertGreaterThan(0, $passwords);
    }

    public function test_obsolete_storefront_templates_are_not_kept_as_alternate_flows(): void
    {
        foreach ([
            'brands/manual/somi.blade.php',
            'products/show.blade.php',
            'shoes/women.blade.php',
            'welcome.blade.php',
            'partials/header-tools.blade.php',
            'partials/top-search.blade.php',
            'product/how.blade.php',
            'new/index.blade.php',
        ] as $view) {
            $this->assertFileDoesNotExist(resource_path('views/'.$view));
        }
    }

    public function test_public_demo_image_directory_contains_only_image_assets(): void
    {
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'avif'];

        foreach (File::allFiles(public_path('assets/img/demo')) as $file) {
            $this->assertContains(strtolower($file->getExtension()), $allowed, $file->getPathname());
        }
    }

    public function test_seeders_directory_does_not_contain_misplaced_http_controllers(): void
    {
        $this->assertFileDoesNotExist(database_path('seeders/ProductController.php'));
    }

    public function test_database_category_navigation_is_rendered_without_html_injection(): void
    {
        $javascript = File::get(public_path('assets/app.js'));

        $this->assertStringContainsString("document.getElementById('martCategoryMenuData')", $javascript);
        $this->assertStringContainsString("JSON.parse(dataCarrier?.dataset.menu || '{}')", $javascript);
        $this->assertStringContainsString('title.textContent = col.title;', $javascript);
        $this->assertStringContainsString('link.textContent = text;', $javascript);
        $this->assertStringNotContainsString('content.innerHTML', $javascript);
    }

    public function test_shared_interactions_are_not_kept_as_inline_template_scripts(): void
    {
        foreach ([
            'partials/chat.blade.php',
            'merchant/catalog/create.blade.php',
            'checkout/partials/payment_methods.blade.php',
            'product/show.blade.php',
            'new-products/index.blade.php',
            'cart/index.blade.php',
        ] as $view) {
            $this->assertStringNotContainsString('<script', File::get(resource_path('views/'.$view)), $view);
        }

        $javascript = File::get(public_path('assets/app.js'));
        $this->assertStringContainsString("document.getElementById('support-widget')", $javascript);
        $this->assertStringContainsString("document.querySelectorAll('input[name=\"product_mode\"]')", $javascript);
        $this->assertStringContainsString("document.querySelectorAll('.pay-card')", $javascript);
        $this->assertStringContainsString("document.querySelector('form.js-add-cart')", $javascript);
        $this->assertStringContainsString("document.getElementById('catsDrawer')", $javascript);
        $this->assertStringContainsString("document.querySelectorAll('.qty-form')", $javascript);
    }

    public function test_blade_templates_do_not_contain_executable_inline_scripts(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/<script(?![^>]*\bsrc\s*=)[^>]*>/i',
                $file->getContents(),
                $file->getPathname(),
            );
        }

        $category = File::get(resource_path('views/category/show.blade.php'));
        $this->assertStringContainsString('JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT', $category);
        $this->assertStringContainsString("JSON.parse(modal.dataset.products || '{}')", File::get(public_path('assets/category.js')));
    }

    public function test_blade_templates_do_not_contain_inline_style_attributes(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $this->assertStringNotContainsString('style="', $file->getContents(), $file->getPathname());
        }
    }

    public function test_shared_storefront_shell_does_not_use_inline_style_attributes(): void
    {
        foreach (['layouts/app.blade.php', 'partials/header.blade.php', 'partials/cart.blade.php'] as $view) {
            $this->assertStringNotContainsString('style="', File::get(resource_path('views/'.$view)), $view);
        }

        $css = File::get(public_path('assets/styles.css'));
        foreach (['.site-emergency-notice', '.site-orders-disabled', '.site-toolbar-wrap', '.logout-inline'] as $selector) {
            $this->assertStringContainsString($selector, $css);
        }
    }

    public function test_private_layout_base_styles_are_external(): void
    {
        $layout = File::get(resource_path('views/layouts/private-finance.blade.php'));

        $this->assertStringNotContainsString('<style', $layout);
        $this->assertStringContainsString("asset('assets/private.css')", $layout);
        $this->assertFileExists(public_path('assets/private.css'));
    }

    public function test_error_pages_share_an_external_stylesheet(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $view = File::get(resource_path("views/errors/{$status}.blade.php"));
            $this->assertStringNotContainsString('<style', $view, (string) $status);
            $this->assertStringContainsString("asset('assets/error.css')", $view, (string) $status);
        }

        $this->assertFileExists(public_path('assets/error.css'));
    }

    public function test_password_recovery_layout_has_no_inline_styles(): void
    {
        $layout = File::get(resource_path('views/auth/password-layout.blade.php'));

        $this->assertStringNotContainsString('<style', $layout);
        $this->assertStringNotContainsString('style="', $layout);
        $this->assertStringContainsString("asset('assets/password-recovery.css')", $layout);
        $this->assertFileExists(public_path('assets/password-recovery.css'));
    }

    public function test_login_and_registration_styles_are_external(): void
    {
        $view = File::get(resource_path('views/auth/account.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/auth.css')", $view);
        $this->assertFileExists(public_path('assets/auth.css'));
    }

    public function test_account_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/auth/profile.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringContainsString("asset('assets/account.css')", $view);
        $this->assertFileExists(public_path('assets/account.css'));
    }

    public function test_contact_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/contact/create.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/contact.css')", $view);
        $this->assertFileExists(public_path('assets/contact.css'));
    }

    public function test_checkout_partial_styles_are_external(): void
    {
        foreach (['checkout/partials/identity_and_address.blade.php', 'checkout/partials/payment_methods.blade.php'] as $viewPath) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringNotContainsString('style="', $view, $viewPath);
        }

        $identity = File::get(resource_path('views/checkout/partials/identity_and_address.blade.php'));
        $this->assertStringContainsString("asset('assets/checkout.css')", $identity);
        $this->assertFileExists(public_path('assets/checkout.css'));
    }

    public function test_product_page_static_styles_are_external(): void
    {
        $view = File::get(resource_path('views/product/show.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringContainsString("asset('assets/product.css')", $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString('fill="{{ $hex }}"', $view);
        $this->assertFileExists(public_path('assets/product.css'));
    }

    public function test_brand_listing_styles_are_external(): void
    {
        $view = File::get(resource_path('views/brands/show.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/brands.css')", $view);
        $this->assertFileExists(public_path('assets/brands.css'));
    }

    public function test_cart_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/cart/index.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/cart.css')", $view);
        $this->assertFileExists(public_path('assets/cart.css'));
    }

    public function test_home_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/home.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/home.css')", $view);
        $this->assertFileExists(public_path('assets/home.css'));
    }

    public function test_new_products_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/new-products/index.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/new-products.css')", $view);
        $this->assertFileExists(public_path('assets/new-products.css'));
    }

    public function test_deals_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/deals/index.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/deals.css')", $view);
        $this->assertFileExists(public_path('assets/deals.css'));
    }

    public function test_policies_page_styles_are_external(): void
    {
        $view = File::get(resource_path('views/content/policies.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/policies.css')", $view);
        $this->assertFileExists(public_path('assets/policies.css'));
    }

    public function test_category_index_styles_are_external(): void
    {
        $view = File::get(resource_path('views/category/index.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/category-index.css')", $view);
        $this->assertFileExists(public_path('assets/category-index.css'));
    }

    public function test_order_history_styles_are_external(): void
    {
        $view = File::get(resource_path('views/orders/history.blade.php'));

        $this->assertStringNotContainsString('<style', $view);
        $this->assertStringNotContainsString('style="', $view);
        $this->assertStringContainsString("asset('assets/order-history.css')", $view);
        $this->assertFileExists(public_path('assets/order-history.css'));
    }

    public function test_address_and_order_confirmation_styles_are_external(): void
    {
        foreach ([
            'address/edit.blade.php' => 'address.css',
            'checkout/confirmation.blade.php' => 'order-confirmation.css',
        ] as $viewPath => $asset) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringNotContainsString('style="', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/{$asset}')", $view, $viewPath);
            $this->assertFileExists(public_path('assets/'.$asset));
        }
    }

    public function test_merchant_catalog_styles_are_external(): void
    {
        foreach (['create', 'edit', 'product-edit', 'change-request', 'index'] as $viewName) {
            $viewPath = 'merchant/catalog/'.$viewName.'.blade.php';
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/merchant-catalog.css')", $view, $viewPath);
        }

        $this->assertFileExists(public_path('assets/merchant-catalog.css'));
    }

    public function test_merchant_profile_orders_and_payment_styles_are_external(): void
    {
        foreach ([
            'merchant/profile.blade.php' => 'merchant-profile.css',
            'merchant/orders/index.blade.php' => 'merchant-orders.css',
            'merchant/orders/show.blade.php' => 'merchant-orders.css',
            'payment/manual.blade.php' => 'manual-payment.css',
        ] as $viewPath => $asset) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/{$asset}')", $view, $viewPath);
            $this->assertFileExists(public_path('assets/'.$asset));
        }
    }

    public function test_ledger_styles_are_external(): void
    {
        foreach ([
            'merchant/ledger/index.blade.php' => 'merchant-ledger.css',
            'admin/ledger/index.blade.php' => 'admin-ledger.css',
        ] as $viewPath => $asset) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/{$asset}')", $view, $viewPath);
            $this->assertFileExists(public_path('assets/'.$asset));
        }
    }

    public function test_withdrawal_styles_are_external(): void
    {
        foreach ([
            'merchant/withdrawals/index.blade.php' => 'merchant-withdrawals.css',
            'admin/withdrawals/index.blade.php' => 'admin-withdrawals.css',
        ] as $viewPath => $asset) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/{$asset}')", $view, $viewPath);
            $this->assertFileExists(public_path('assets/'.$asset));
        }
    }

    public function test_financial_configuration_styles_are_external(): void
    {
        foreach ([
            'admin/commissions/index.blade.php' => 'admin-commissions.css',
            'admin/payment-methods/index.blade.php' => 'admin-payment-methods.css',
        ] as $viewPath => $asset) {
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/{$asset}')", $view, $viewPath);
            $this->assertFileExists(public_path('assets/'.$asset));
        }
    }

    public function test_support_inbox_styles_are_external(): void
    {
        foreach (['index', 'show'] as $viewName) {
            $viewPath = 'admin/contact-messages/'.$viewName.'.blade.php';
            $view = File::get(resource_path('views/'.$viewPath));
            $this->assertStringNotContainsString('<style', $view, $viewPath);
            $this->assertStringContainsString("asset('assets/admin-contact-messages.css')", $view, $viewPath);
        }

        $this->assertFileExists(public_path('assets/admin-contact-messages.css'));
    }

    public function test_shared_javascript_does_not_generate_inline_styles(): void
    {
        $javascript = File::get(public_path('assets/app.js'));

        $this->assertStringNotContainsString('.style.', $javascript);
        $this->assertStringNotContainsString('.style,', $javascript);
        $this->assertStringNotContainsString('style="', $javascript);
        $this->assertStringNotContainsString('cssText', $javascript);
    }
}
