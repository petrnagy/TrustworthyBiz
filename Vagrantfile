# -*- mode: ruby -*-
# vi: set ft=ruby :

# All Vagrant configuration is done below. The "2" in Vagrant.configure
# configures the configuration version (we support older styles for
# backwards compatibility). Please don't change it unless you know what
# you're doing.
Vagrant.configure("2") do |config|
  
    config.vm.box = "ubuntu/trusty64"

    config.vm.provider "virtualbox" do |v|
        v.customize ["modifyvm", :id, "--memory", "2048"]
    end

    config.vm.network :forwarded_port, host: 8082, guest: 80
    config.vm.network :forwarded_port, host: 3306, guest: 3306

    #config.vm.provision :shell, path: "provision.sh"

    config.vm.network :private_network, ip: "10.11.12.111"
    config.vm.synced_folder ".", "/vagrant", type: "nfs", :nfs => { :mount_options => ["dmode=777","fmode=777","actimeo=1"] }

    config.vm.provider :virtualbox do |vb|
        #vb.gui = true
    end

end
