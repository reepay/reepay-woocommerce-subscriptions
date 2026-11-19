#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-"127.0.0.1"}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

TMPDIR=$(php ./bin/set-php-temp-path.php)
TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\-(beta|RC)[0-9]+$ ]]; then
	WP_BRANCH=${WP_VERSION%\-*}
	WP_TESTS_TAG="branches/$WP_BRANCH"
elif [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
	WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
	if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
		WP_TESTS_TAG="tags/${WP_VERSION%??}"
	else
		WP_TESTS_TAG="tags/$WP_VERSION"
	fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
	WP_TESTS_TAG="trunk"
else
	download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
	LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
	if [[ -z "$LATEST_VERSION" ]]; then
		echo "Latest WordPress version could not be found"
		exit 1
	fi
	WP_TESTS_TAG="tags/$LATEST_VERSION"
fi
set -ex

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

	if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
		mkdir -p $TMPDIR/wordpress-nightly
		download https://wordpress.org/nightly-builds/wordpress-latest.zip  $TMPDIR/wordpress-nightly/wordpress-nightly.zip
		unzip -q $TMPDIR/wordpress-nightly/wordpress-nightly.zip -d $TMPDIR/wordpress-nightly/
		mv $TMPDIR/wordpress-nightly/wordpress/* $WP_CORE_DIR
	else
		if [ $WP_VERSION == 'latest' ]; then
			local ARCHIVE_NAME='latest'
		elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+ ]]; then
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		else
			local ARCHIVE_NAME="wordpress-$WP_VERSION"
		fi
		download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
		tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
	fi

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i.bak'
	else
		local ioption='-i'
	fi

	if [ ! -d $WP_TESTS_DIR ]; then
		mkdir -p $WP_TESTS_DIR
		rm -rf $WP_TESTS_DIR/{includes,data}
		svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
		svn export --quiet --ignore-externals https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
	fi

	if [ ! -f wp-tests-config.php ]; then
		download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
		WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
		sed $ioption "s#dirname( __FILE__ ) . '/src/'#'$WP_CORE_DIR/'#" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s#__DIR__ . '/src/'#'$WP_CORE_DIR/'#" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi
}

recreate_db() {
	shopt -s nocasematch
	if [[ $1 =~ ^(y|yes)$ ]]
	then
		mysqladmin drop $DB_NAME -f --user="$DB_USER" --password="$DB_PASS"$EXTRA
		create_db
		echo "Recreated the database ($DB_NAME)."
	else
		echo "Leaving the existing database ($DB_NAME) in place."
	fi
	shopt -u nocasematch
}

create_db() {
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

install_db() {
	if [ ${SKIP_DB_CREATE} = "true" ]; then
		return 0
	fi

	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]}
	local DB_SOCK_OR_PORT=${PARTS[1]}
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ]; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ]; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ]; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	if [ $(mysql --user="$DB_USER" --password="$DB_PASS"$EXTRA --execute='show databases;' | grep ^$DB_NAME$) ]
	then
		echo "Reinstalling will delete the existing test database ($DB_NAME)"
		read -p 'Are you sure you want to proceed? [y/N]: ' DELETE_EXISTING_DB
		recreate_db $DELETE_EXISTING_DB
	else
		create_db
	fi
}

install_dependencies() {
	WP_PLUGIN_DIR=$(pwd)

	cd "$WP_CORE_DIR"
	curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar

	[ ! -f ./wp-config.php ] && php wp-cli.phar core config --dbname=$DB_NAME --dbuser=$DB_USER --dbpass=$DB_PASS --dbhost=$DB_HOST --dbprefix=wptests_

	# The sibling plugin's script installs WordPress by importing a pre-built
	# tests/data/db.sql dump instead of calling `wp core install` -- this plugin
	# has no such dump, so install fresh via wp-cli instead.
	php wp-cli.phar core is-installed || php wp-cli.phar core install --url="http://localhost" --title="PHPUnit" --admin_user=admin --admin_password=admin --admin_email=admin@example.org --skip-email

	php wp-cli.phar config set --raw WP_DEBUG true
	php wp-cli.phar config set --raw WP_DEBUG_LOG true
	php wp-cli.phar config set --raw WP_DEBUG_DISPLAY true

	php wp-cli.phar plugin install woocommerce --activate --force

	# reepay-woocommerce-payment's GitHub repo folder name already matches the
	# slug PLUGINS_STATE expects (reepay-woocommerce-payment/reepay-woocommerce-payment.php),
	# so no rename is needed here (unlike the sibling plugin's script, which renames
	# reepay-woocommerce-subscriptions -> reepay-subscriptions-for-woocommerce).
	# Its own repo gitignores vendor/, so a plain zip download never includes
	# vendor/autoload.php -- run composer install there before activating, or the
	# plugin fatals immediately (Class "Reepay\Checkout\Plugin\Statistics" not found).
	# GitHub's codeload zip endpoint intermittently fails wp-cli's unzip step
	# with PCLZIP_ERR_BAD_FORMAT (confirmed, recurring, unrelated to this
	# script or environment -- a GitHub-side quirk). Don't let `set -e` abort
	# the whole script on that one flaky download -- try the fast path, and
	# if it didn't actually produce the plugin folder, fall back to a manual
	# curl+unzip, which has never failed the same way.
	set +e
	php wp-cli.phar plugin install https://github.com/reepay/reepay-woocommerce-payment/archive/refs/heads/master.zip --force
	set -e

	if [ ! -d wp-content/plugins/reepay-woocommerce-payment ]; then
		echo "wp-cli's zip install didn't produce wp-content/plugins/reepay-woocommerce-payment -- falling back to a manual download."
		curl -sL -o /tmp/reepay-pay-download.zip https://github.com/reepay/reepay-woocommerce-payment/archive/refs/heads/master.zip
		unzip -q -o /tmp/reepay-pay-download.zip -d wp-content/plugins/
		# Remove any stale folder from a previous run first -- `mv src dest`
		# moves INTO an existing non-empty dest instead of replacing it,
		# which fails with "Directory not empty" when this script is
		# re-run on top of an already-populated /tmp/wordpress (the normal,
		# expected case -- not everyone wipes it every time).
		rm -rf wp-content/plugins/reepay-woocommerce-payment
		mv wp-content/plugins/reepay-woocommerce-payment-master wp-content/plugins/reepay-woocommerce-payment
		rm -f /tmp/reepay-pay-download.zip
	fi

	( cd wp-content/plugins/reepay-woocommerce-payment && composer install --no-dev --no-interaction )
	php wp-cli.phar plugin activate reepay-woocommerce-payment

	# Unlike a GitHub-sourced zip, wp-cli does not auto-rename a custom-hosted
	# zip's folder to match a slug -- install without --activate (so it's never
	# activated under the wrong folder name), rename, then activate under the
	# slug PLUGINS_STATE expects.
	php wp-cli.phar plugin install http://billwerkwoodev.phhw-190202.cust.powerhosting.dk/subdomains/assets/woocommerce-subscriptions-for-phpunit-main.zip --force
	# Same re-run idempotency issue as above: remove any stale folder from a
	# previous run first, or `mv` fails with "Directory not empty" instead
	# of replacing it.
	rm -rf ./wp-content/plugins/woocommerce-subscriptions
	mv ./wp-content/plugins/woocommerce-subscriptions-for-phpunit-main ./wp-content/plugins/woocommerce-subscriptions
	php wp-cli.phar plugin activate woocommerce-subscriptions

	php wp-cli.phar plugin list

	cd "$WP_PLUGIN_DIR"
}

install_wp
install_test_suite
install_db
install_dependencies
