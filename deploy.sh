#! /bin/bash
#
# Script to deploy from Github to WordPress.org Plugin Repository

#prompt for plugin slug
echo -e "Plugin Slug: \c"
read PLUGINSLUG

# main config, set off of plugin slug
CURRENTDIR=`pwd`
CURRENTDIR="$CURRENTDIR/$PLUGINSLUG"
MAINFILE="$PLUGINSLUG.php"

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # path to a temp SVN repo. No trailing slash required and don't add trunk.
SVNURL="http://plugins.svn.wordpress.org/$PLUGINSLUG/" # Remote SVN repo on WordPress.org, with no trailing slash
SVNUSER="benbalter" # your svn username

# Let's begin...
echo ".........................................."
echo 
echo "Preparing to deploy WordPress plugin"
echo 
echo ".........................................."
echo 

# Check version in readme.txt is the same as plugin file
NEWVERSION1=`grep "^Stable tag" $GITPATH/readme.txt | awk -F' ' '{print $3}'`
echo "readme version: $NEWVERSION1"
NEWVERSION2=`grep "^Version" $GITPATH/$MAINFILE | awk -F' ' '{print $2}'`
echo "$MAINFILE version: $NEWVERSION2"

if [ "$NEWVERSION1" != "$NEWVERSION2" ]; then echo "Versions don't match. Exiting...."; exit 1; fi

echo "Versions match in readme.txt and PHP file. Let's proceed..."

cd $GITPATH
echo -e "Enter a commit message for this new version: \c"
read COMMITMSG
git commit -am "$COMMITMSG"

echo "Tagging new version in git"
git tag -a "$NEWVERSION1" -m "Tagging version $NEWVERSION1"

echo "Pushing latest commit to origin, with tags"
git push origin master
git push origin master --tags

echo 
echo "Creating local copy of SVN repo ..."
svn co $SVNURL $SVNPATH

echo "Ignoring github specific files and deployment script"
svn propset svn:ignore "deploy.sh
README.md
.git
.gitignore" "$SVNPATH/trunk/"

echo "Exporting the HEAD of master from git to the trunk of SVN"
git checkout-index -a -f --prefix=$SVNPATH/trunk/

echo "Changing directory to SVN and committing to trunk"
cd $SVNPATH/trunk/
# Add all new files that are not set to be ignored
svn status | grep -v "^.[ \t]*\..*" | grep "^?" | awk '{print $2}' | xargs svn add
svn commit --username=$SVNUSER -m "$COMMITMSG"

echo "Creating new SVN tag & committing it"
cd $SVNPATH
svn copy trunk/ tags/$NEWVERSION1/
cd $SVNPATH/tags/$NEWVERSION1
svn commit --username=$SVNUSER -m "Tagging version $NEWVERSION1"

echo "Removing temporary directory $SVNPATH"
rm -fr $SVNPATH/

echo "*** FIN ***"