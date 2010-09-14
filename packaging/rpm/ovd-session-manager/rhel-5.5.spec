Summary: Ulteo OVD - Session Manager
Name: ovd-session-manager
Version: @VERSION@
Release: 1
License: GPL2
Group: Applications/System
Source0: %{name}-%{version}.tar.gz
BuildArch: noarch
Requires: httpd, curl, php, php-ldap, php-mysql, php-xml, php-mbstring, php-pear, php-gd, php-pecl-imagick, ovd-applets

%description
Ulteo OVD Session Manager is Ulteo OVD portal.

%prep
%setup -q

%build
./configure --prefix=/usr --sysconfdir=/etc --localstatedir=/var
make

%install
make DESTDIR=$RPM_BUILD_ROOT install
# install the logrotate example
mkdir -p $RPM_BUILD_ROOT/etc/logrotate.d
install -m 0644 examples/ulteo-sm.logrotate $RPM_BUILD_ROOT/etc/logrotate.d/sessionmanager

%clean
rm -rf $RPM_BUILD_ROOT

%files
%defattr(-,root,root)
/usr/*
%config /etc/ulteo/sessionmanager/apache2.conf
%config /etc/ulteo/sessionmanager/sessionmanager.cron
%config /etc/logrotate.d/sessionmanager
%defattr(0660,apache,root)
%config /etc/ulteo/sessionmanager/config.inc.php
%defattr(2770,apache,root)
/var/log/ulteo/*
/var/spool/ulteo/*

%changelog
* Fri Jan 02 2009 Gauvain Pocentek <gauvain@ulteo.com> 1.0~svn00130-1
- Initial release
