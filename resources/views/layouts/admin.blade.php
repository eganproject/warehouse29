<!DOCTYPE html>
<html lang="en">

<head>
    <base href="">
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', 'Import Analytics')</title>
    <link rel="shortcut icon" href="{{ asset('metronic/media/logos/favicon.png') }}" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
		<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" />
    <link href="{{ asset('metronic/css/style.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/global/plugins.bundle.css') }}" rel="stylesheet" type="text/css" />
    <link href="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
    <style>
        .select2-container .select2-selection--single {
            height: 42px;
            padding: 0.5rem 1rem;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }

        .form-select.form-select-solid+.select2-container .select2-selection--single {
            background-color: #f5f8fa;
            border-color: #f5f8fa;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 30px;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 12px;
        }

        .select2-container .select2-selection--multiple {
            min-height: 42px;
            border-radius: 0.475rem;
            border: 1px solid #e4e6ef;
        }
    </style>
    @stack('styles')
    @yield('styles')
    <meta name="description" content="Import Analytics dashboard" />
    <meta name="keywords" content="import analytics,dashboard,analytics" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="website" />
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled">
    <div class="d-flex flex-column flex-root">
        <!--begin::Page-->
        <div class="page d-flex flex-row flex-column-fluid">
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                @include('layouts.partials.header')
                @include('layouts.partials.toolbar')

                {{-- <div class="toolbar py-5 py-lg-5" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-xxl d-flex flex-stack flex-wrap">
                        <div class="page-title d-flex flex-column me-3">
                            <h1 class="d-flex text-dark fw-bolder my-1 fs-3">@yield('page_title', 'Dashboard')</h1>
                            <ul class="breadcrumb breadcrumb-dot fw-bold text-gray-600 fs-7 my-1">
                                @yield('page_breadcrumbs')
                            </ul>
                        </div>
                        <div class="d-flex align-items-center gap-2 my-1">
                            @yield('page_actions')
                        </div>
                    </div>
                </div> --}}

                <div id="kt_content_container" class="d-flex flex-column-fluid align-items-start container-xxl">
                    <!--begin::Post-->
                    <div class="content flex-row-fluid" id="kt_content">
                        @yield('content')

                    </div>
                </div>

                @include('layouts.partials.footer')
            </div>
        </div>
    </div>

    <script src="{{ asset('metronic/plugins/global/plugins.bundle.js') }}"></script>
    <script src="{{ asset('metronic/js/scripts.bundle.js') }}"></script>
    <script src="{{ asset('metronic/plugins/custom/datatables/datatables.bundle.js') }}"></script>
    <script>
        (function () {
            const applyModalStaticBackdrop = (modalEl) => {
                if (!modalEl || !modalEl.setAttribute) return;
                modalEl.setAttribute('data-bs-backdrop', 'static');
            };

            const enforceModalBackdrops = () => {
                document.querySelectorAll('.modal').forEach(applyModalStaticBackdrop);
            };

            const enforceSweetalertBackdrop = () => {
                if (!window.Swal || window.__swalNoOutsideClickApplied) {
                    return;
                }
                const originalFire = window.Swal.fire.bind(window.Swal);
                window.Swal.fire = function (...args) {
                    if (args.length === 1 && args[0] && typeof args[0] === 'object' && !Array.isArray(args[0])) {
                        const options = { ...args[0], allowOutsideClick: false };
                        return originalFire(options);
                    }
                    const options = {
                        title: args[0],
                        text: args[1],
                        icon: args[2],
                        allowOutsideClick: false,
                    };
                    return originalFire(options);
                };
                window.__swalNoOutsideClickApplied = true;
            };

            enforceModalBackdrops();
            enforceSweetalertBackdrop();

            if (!window.AppSwal) {
                window.AppSwal = {
                    confirm: (title, options = {}) => {
                        if (!window.Swal) {
                            return Promise.resolve(window.confirm(title));
                        }
                        const confirmButtonText = options.confirmButtonText || 'Ya';
                        const cancelButtonText = options.cancelButtonText || 'Batal';
                        const confirmButtonType = options.confirmButtonType || 'primary';
                        return window.Swal.fire({
                            title,
                            icon: options.icon || 'warning',
                            showCancelButton: true,
                            confirmButtonText,
                            cancelButtonText,
                            buttonsStyling: false,
                            customClass: {
                                confirmButton: `btn btn-${confirmButtonType}`,
                                cancelButton: 'btn btn-light',
                            },
                        }).then((result) => result.isConfirmed === true);
                    },
                    error: (message, title = 'Error') => {
                        if (window.Swal) {
                            return window.Swal.fire(title, message || 'Terjadi kesalahan', 'error');
                        }
                        alert(message || 'Terjadi kesalahan');
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                enforceModalBackdrops();
                if (document.body && !window.__modalBackdropObserver) {
                    const observer = new MutationObserver((mutations) => {
                        mutations.forEach((mutation) => {
                            mutation.addedNodes.forEach((node) => {
                                if (!node || node.nodeType !== 1) return;
                                if (node.classList?.contains('modal')) {
                                    applyModalStaticBackdrop(node);
                                }
                                node.querySelectorAll?.('.modal')?.forEach(applyModalStaticBackdrop);
                            });
                        });
                    });
                    observer.observe(document.body, { childList: true, subtree: true });
                    window.__modalBackdropObserver = observer;
                }
            });
        })();
    </script>
    @stack('scripts')
    @yield('scripts')
</body>

</html>
