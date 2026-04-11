   const arrows = document.querySelectorAll(".arrow");

    arrows.forEach((arrow) => {
        arrow.addEventListener("click", (e) => {
            const arrowParent = e.target.closest(".arrow").parentElement.parentElement;
            arrowParent.classList.toggle("showMenu");
        });
    });

    const sidebar = document.querySelector(".sidebar");
    const sidebarBtn = document.querySelector(".sidebar-toggle");

    sidebarBtn.addEventListener("click", () => {
        sidebar.classList.toggle("close-main");
        const main = document.querySelector(".main-container");
        main.classList.toggle("close-main");

        const sideLink = document.querySelector(".link_name");
        if(sidebar.classList.contains('close-main')){
            sideLink.style.display = 'none';
        }else{
            sideLink.style.display = 'block';
        }
    });

    function handleSidebarOnResize() {
        setTimeout(() => {
            const sidebar = document.querySelector('.sidebar');
            const mainContainer = document.querySelector('.main-container');
            if (window.innerWidth <= 768) { 
                sidebar.classList.add('close-main');
                mainContainer.classList.add('close-main');
            } 
        }, 500); 
    }

    handleSidebarOnResize();
    window.addEventListener('resize', handleSidebarOnResize);
