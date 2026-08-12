import { Head, Link, usePage } from '@inertiajs/react';
import {
    FileText,
    Fuel,
    LayoutGrid,
    ParkingSquare,
    Receipt,
    Settings as SettingsIcon,
    Truck,
    Users,
} from 'lucide-react';
import { useEffect } from 'react';
import type { PropsWithChildren, ReactNode } from 'react';
import { toast } from 'sonner';

import type { Auth } from '@/types';

/**
 * The admin console shell — a direct port of the mockup's dark sidebar.
 *
 * Plain elements and the ds-* classes from resources/css/ds.css, not a
 * component library restyled into looking like a fuel depot.
 */

type SharedProps = {
    auth: Auth;
    flash: { success: string | null; error: string | null };
};

type NavItem = {
    label: string;
    href: string;
    icon: ReactNode;
    active: boolean;
    visible: boolean;
};

type NavGroup = {
    title: string;
    items: NavItem[];
};

export default function DsAdminLayout({
    children,
    title,
    breadcrumb,
    heading,
    actions,
}: PropsWithChildren<{
    title: string;
    breadcrumb: string;
    heading: string;
    actions?: ReactNode;
}>) {
    const page = usePage<SharedProps>();
    const { auth, flash } = page.props;
    const can = auth.can;
    const url = page.url;

    // Flash messages arrive as shared props on every response, including the
    // silent autosave patches — those send null and stay quiet.
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const groups: NavGroup[] = [
        {
            title: 'Overview',
            items: [
                {
                    label: 'Dashboard',
                    href: '/admin',
                    icon: <LayoutGrid />,
                    active: url === '/admin',
                    visible: !auth.user || auth.role !== 'attendant',
                },
                {
                    label: 'Diesel pricing',
                    href: '/admin/pricing',
                    icon: <Fuel />,
                    active: url.startsWith('/admin/pricing'),
                    visible: can.viewPrices,
                },
            ],
        },
        {
            title: 'Sales',
            items: [
                {
                    label: 'Quotes',
                    href: '/admin/documents?type=quote',
                    icon: <FileText />,
                    active:
                        url.startsWith('/admin/documents') &&
                        url.includes('type=quote'),
                    visible: can.viewDocuments,
                },
                {
                    label: 'Invoices',
                    href: '/admin/documents?type=invoice',
                    icon: <Receipt />,
                    active:
                        url.startsWith('/admin/documents') &&
                        url.includes('type=invoice'),
                    visible: can.viewDocuments,
                },
                {
                    label: 'Customers',
                    href: '/admin/customers',
                    icon: <Users />,
                    active: url.startsWith('/admin/customers'),
                    visible: can.viewCustomers,
                },
            ],
        },
        {
            title: 'Operations',
            items: [
                {
                    label: 'All documents',
                    href: '/admin/documents',
                    icon: <Truck />,
                    active: url === '/admin/documents',
                    visible: can.viewDocuments,
                },
                {
                    label: 'Yard bookings',
                    href: '/admin/yard',
                    icon: <ParkingSquare />,
                    active: url.startsWith('/admin/yard'),
                    visible: can.viewYard,
                },
                {
                    label: 'Settings',
                    href: '/admin/settings',
                    icon: <SettingsIcon />,
                    active: url.startsWith('/admin/settings'),
                    visible: can.viewSettings,
                },
            ],
        },
    ];

    return (
        <>
            <Head title={title} />

            <div className="ds-admin">
                <aside className="ds-side">
                    <div className="brand">
                        <Link href="/admin" className="ds-logo">
                            <div className="ds-mark">
                                <span className="d">D</span>
                                <span>S</span>
                            </div>
                            <div className="ds-slash" />
                            <div>
                                <div className="w1">DYNAMIC</div>
                                <div className="w2">SOLUTIONS</div>
                            </div>
                        </Link>
                        <div className="tagsm">ADMIN CONSOLE</div>
                    </div>

                    {groups.map((group) => {
                        const visible = group.items.filter(
                            (item) => item.visible,
                        );

                        if (visible.length === 0) {
                            return null;
                        }

                        return (
                            <div key={group.title}>
                                <div className="grp">{group.title}</div>
                                {visible.map((item) => (
                                    <Link
                                        key={item.label}
                                        href={item.href}
                                        className={
                                            item.active ? 'act' : undefined
                                        }
                                    >
                                        {item.icon}
                                        {item.label}
                                    </Link>
                                ))}
                            </div>
                        );
                    })}

                    <div className="who">
                        <b>{auth.user?.name}</b>
                        {roleDescription(auth.role)}
                        <Link
                            href="/settings/profile"
                            className="ds-linkred"
                            style={{ display: 'block', marginTop: 10 }}
                        >
                            My account →
                        </Link>
                    </div>
                </aside>

                <div className="ds-main">
                    <div className="ds-crumb">{breadcrumb}</div>
                    <div className="ds-mainhead">
                        <h1>{heading}</h1>
                        {actions}
                    </div>

                    {children}
                </div>
            </div>
        </>
    );
}

function roleDescription(role: Auth['role']): string {
    switch (role) {
        case 'owner':
            return 'Owner · full access';
        case 'attendant':
            return 'Attendant · yard only';
        default:
            return 'Admin · documents and customers';
    }
}
