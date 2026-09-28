import { Link } from '@inertiajs/react';
import { BookOpenText, Building2, FileCheck2, FileText, LayoutDashboard, LogOut, Menu, Settings2, Users, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import type { AuthUser } from '../types';

type Section = 'overview' | 'companies' | 'accounting' | 'invoices' | 'cabinet' | 'users';
type NavigationItem = {
  label: string;
  href: string;
  section: Section;
  icon: typeof LayoutDashboard;
};
type Props = {
  activeSection: Section;
  children: ReactNode;
  onNavigate?: (href: string) => void;
  onLogout?: () => void;
  user?: AuthUser | null;
  cabinetName?: string | null;
  canManageCabinet?: boolean;
};

const baseNavigation: NavigationItem[] = [
  { label: 'Vue d’ensemble', href: '/', section: 'overview', icon: LayoutDashboard },
  { label: 'Sociétés', href: '/companies', section: 'companies', icon: Building2 },
  { label: 'Factures', href: '/invoices', section: 'invoices', icon: FileText },
  { label: 'Données comptables', href: '/accounting-data', section: 'accounting', icon: BookOpenText },
];

function initials(name?: string | null): string {
  if (!name) return 'CF';
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

export default function AppShell({
  activeSection,
  children,
  onNavigate,
  onLogout,
  user,
  cabinetName,
  canManageCabinet = false,
}: Props) {
  const [mobileOpen, setMobileOpen] = useState(false);
  const navigation: NavigationItem[] = [
    ...baseNavigation,
    ...(canManageCabinet ? [
      { label: 'Cabinet', href: '/cabinet/settings', section: 'cabinet' as const, icon: Settings2 },
      { label: 'Utilisateurs', href: '/cabinet/users', section: 'users' as const, icon: Users },
    ] : []),
  ];
  const sectionLabels: Record<Section, string> = {
    overview: 'Vue d’ensemble',
    companies: 'Sociétés',
    accounting: 'Données comptables',
    invoices: 'Factures',
    cabinet: 'Cabinet',
    users: 'Utilisateurs',
  };

  const renderLink = (item: NavigationItem) => {
    const Icon = item.icon;
    const isActive = activeSection === item.section;
    const className = `sidebar-link ${isActive ? 'sidebar-link--active' : ''}`;
    const handleClick = () => setMobileOpen(false);
    const content = (
      <>
        <Icon size={18} strokeWidth={1.9} />
        <span>{item.label}</span>
      </>
    );

    if (onNavigate) {
      return (
        <a
          key={item.href}
          href={item.href}
          className={className}
          onClick={(event) => {
            event.preventDefault();
            handleClick();
            onNavigate(item.href);
          }}
        >
          {content}
        </a>
      );
    }

    return <Link key={item.href} href={item.href} className={className} onClick={handleClick}>{content}</Link>;
  };

  return (
    <div className="app-frame">
      <button
        className={`sidebar-scrim ${mobileOpen ? 'sidebar-scrim--visible' : ''}`}
        aria-label="Fermer le menu"
        onClick={() => setMobileOpen(false)}
      />
      <aside className={`sidebar ${mobileOpen ? 'sidebar--open' : ''}`}>
        <div className="brand-lockup">
          <div className="brand-mark"><span>c</span><i /></div>
          <div>
            <div className="brand-name">compta<span>flow</span></div>
            <div className="brand-caption">PLATEFORME COMPTABLE</div>
          </div>
          <button className="sidebar-close" aria-label="Fermer le menu" onClick={() => setMobileOpen(false)}>
            <X size={20} />
          </button>
        </div>

        <div className="workspace-switcher">
          <div className="workspace-avatar">{initials(cabinetName || user?.name)}</div>
          <div className="workspace-copy">
            <strong>{cabinetName || 'Espace cabinet'}</strong>
            <span>{canManageCabinet ? 'Administration du cabinet' : 'Accès cabinet'}</span>
          </div>
        </div>

        <nav className="sidebar-nav" aria-label="Navigation principale">
          <div className="nav-caption">ESPACE DE TRAVAIL</div>
          {navigation.map(renderLink)}
        </nav>

        <div className="sidebar-bottom">
          <div className="workflow-card">
            <div className="workflow-card__icon"><FileCheck2 size={15} /></div>
            <div className="workflow-card__copy">
              <strong>Un espace par société</strong>
              <span>Les accès comptables sont gérés au niveau de chaque entreprise.</span>
            </div>
          </div>
          <div className={`profile-row ${onLogout ? 'profile-row--interactive' : ''}`}>
            <div className="profile-avatar">{initials(user?.name)}</div>
            <div className="profile-copy">
              <strong>{user?.name || 'ComptaFlow'}</strong>
              <span>{user?.email || 'Aperçu de démonstration'}</span>
            </div>
            {onLogout && <button className="logout-button" onClick={onLogout} aria-label="Se déconnecter"><LogOut size={15} /></button>}
          </div>
        </div>
      </aside>

      <div className="main-column">
        <header className="topbar">
          <button className="mobile-menu-button" aria-label="Ouvrir le menu" onClick={() => setMobileOpen(true)}>
            <Menu size={21} />
          </button>
          <div className="topbar-breadcrumb">
            <span>{cabinetName || 'Espace cabinet'}</span>
            <span className="breadcrumb-divider">/</span>
            <strong>{sectionLabels[activeSection]}</strong>
          </div>
          <div className="topbar-right">
            <span className="topbar-status"><span /> Accès sécurisé</span>
            <div className="topbar-avatar">{initials(user?.name)}</div>
          </div>
        </header>

        <main className="page-content">{children}</main>
      </div>
    </div>
  );
}
