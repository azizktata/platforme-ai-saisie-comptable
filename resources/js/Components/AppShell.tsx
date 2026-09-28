import { Link } from '@inertiajs/react';
import { FileCheck2, FileText, LayoutDashboard, LogOut, Menu, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';
import type { AuthUser } from '../types';

type Section = 'overview' | 'documents';
type Props = {
  activeSection: Section;
  children: ReactNode;
  onNavigate?: (href: string) => void;
  onLogout?: () => void;
  pendingReviewCount?: number;
  successMessage?: string | null;
  user?: AuthUser | null;
};

const navigation = [
  { label: 'Vue d’ensemble', href: '/', section: 'overview' as const, icon: LayoutDashboard },
  { label: 'Documents', href: '/documents', section: 'documents' as const, icon: FileText },
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
  pendingReviewCount,
  successMessage,
  user,
}: Props) {
  const [mobileOpen, setMobileOpen] = useState(false);

  const renderLink = (item: (typeof navigation)[number]) => {
    const Icon = item.icon;
    const className = `sidebar-link ${activeSection === item.section ? 'sidebar-link--active' : ''}`;
    const handleClick = () => setMobileOpen(false);
    const count = item.section === 'documents' ? pendingReviewCount : undefined;
    const content = (
      <>
        <Icon size={18} strokeWidth={1.9} />
        <span>{item.label}</span>
        {count !== undefined && <span className="sidebar-link__count">{count}</span>}
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
            <div className="brand-caption">GESTION COMPTABLE</div>
          </div>
          <button className="sidebar-close" aria-label="Fermer le menu" onClick={() => setMobileOpen(false)}>
            <X size={20} />
          </button>
        </div>

        <div className="workspace-switcher">
          <div className="workspace-avatar">{initials(user?.name)}</div>
          <div className="workspace-copy">
            <strong>{user?.name || 'Espace comptable'}</strong>
            <span>{user ? 'Espace sécurisé' : 'Mode démonstration'}</span>
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
              <strong>Un flux clair, de bout en bout</strong>
              <span>Importez, vérifiez et comptabilisez vos factures.</span>
            </div>
          </div>
          <div className={`profile-row ${onLogout ? 'profile-row--interactive' : ''}`}>
            <div className="profile-avatar">{initials(user?.name)}</div>
            <div className="profile-copy">
              <strong>{user?.name || 'ComptaFlow'}</strong>
              <span>{user?.email || 'Aperçu interactif'}</span>
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
          <div className="topbar-breadcrumb"><span>Espace comptable</span><span className="breadcrumb-divider">/</span><strong>{activeSection === 'overview' ? 'Vue d’ensemble' : 'Documents'}</strong></div>
          <div className="topbar-right">
            <span className="topbar-status"><span /> Espace sécurisé</span>
            <div className="topbar-avatar">{initials(user?.name)}</div>
          </div>
        </header>

        <main className="page-content">
          {successMessage && (
            <div className="flash-message" role="status">
              <span className="flash-message__dot" />
              {successMessage}
            </div>
          )}
          {children}
        </main>
      </div>
    </div>
  );
}
