# Deployer
Modular and Super Basic Webhook Deployer.
Maybe you don't need all the bells and whistles and just want to deploy every time you push to GitHub.
This small app will do that for you.

## TL;DR Installation
Out of the box, you can just spool a server pointing to `web/index.php` and run `$ composer --install`
It will accept any `POST` to mydeployer.com/{project}/{environment}.
It will look for a `config.json` file in the root directory of the source (where composer.json is). 
This file should not be writable by the server. If it is, it will refuse it.

Expected format for the config file is the following

```json
{
      "project1" :{
        "environment1" :{
            "branch": "master",
            "accepted_pushers" : ["patrick@flagshipcompany.com"],
            "commands" : [
                "composer.phar install --no-dev 2>&1"
            ],
            "project_path" : "/var/www/project1",
            "project_name" : "My Super Web App",
            "notify_emails" : ["patrick@flagshipcompany.com"],
            "from_email" : "patrick@flagshipcompany.com",
            "secret": "super_duper_secret"
        },
        "environment2" :{
            "branch": "test",
            ...
        }
    },

    "project2" :{
        ...
    }

}
```
If you hit `mydeployer.com/project1/environment1`, it will load the config example above.

 * Your project folder needs to be cloned already and be owned by your server's user (apache or httpd ususally for CentOs and Ubuntu respectively)
 * A deploy key needs to be added to the repository on GitHub
 * A webhook needs to be installed as `application/x-www-form-urlencoded`, just the push event, with the `secret` chosen in the config file for that project and environment.
 * And that's it!


## CentOS Installation for GitHub
**Please contribute for other servers!**
### Prepare the project's folder

First we need to create a ssh profile for the apache user.
```bash
# mkdir /var/www/.ssh
# touch /var/www/.ssh/config
# chown -R apache:apache /var/www/.ssh
# chmod 0600 /var/www/.ssh/config
```
This last step is very important, config should only be rw by apache only.

Then install Git if not done already
```bash
# yum install git
```

Then we need to create a ssh key for apache. Those steps will prepare multiple SSH keys so you can release multiple github app on the same server
Since apache doesn't have an interactive shell you need to go through root.

```bash
# sudo -u apache ssh-keygen -t rsa -f /var/www/.ssh/id_rsa.PROJECT_NAME -N ''
```
Replace project name by the actual name of your project.


```bash
# vim /var/www/.ssh/config
```
and add a host:

```
Host PROJECT_NAME-github
  HostName github.com
  User apache
  IdentityFile /var/www/.ssh/id_rsa.PROJECT_NAME
```
and save the file (`:wq`). I like to keep the github at the end to know where it's going.

Time to give the public key to Github
```bash
# cat /var/www/.ssh/id_rsa.PROJECT_NAME.pub
```
And copy/paste the output into your project's deploy keys. for naming I suggest the environnment it's being done for, e.g `production` or `testing`

You can now clone the source of your project!
```bash
# mkdir /var/www/PROJECT_NAME
# chown apache:apache /var/www/PROJECT_NAME
# sudo -u apache git clone git@PROJECT_NAME-github:MY-GITHUB-ORG/MY-PROJECT-NAME.git /var/www/PROJECT_NAME
```

eg:  `sudo -u apache git clone git@deployer-github:flagshipcompany/deployer.git /var/www/deployer`

AND IT'S DONE! Feeewww.

Now everytime you push to PROJECT_NAME, the deployer will automatically get the freshest version of the code for you, run all the commands you passed and send an email that recaps everything.

## Caveat
With the default config provider (flat file) it will expose the config to anything that runs PHP. if you're on shared server, somebody could guess the file path and read it.