// import React from "react";

// const ForgetPassword = () => {
//   return (
//     <Modal
//       open={userAuth.open && userAuth.type === "forgetPassword"}
//       width={400}
//       className="bg-white rounded-xl"
//     >
//       <ToastContainer
//         position="top-center"
//         theme="colored"
//         autoClose={1500}
//         hideProgressBar={true}
//         pauseOnHover
//         className="toast_notification"
//         transition={Slide}
//         closeButton={false}
//       />
//       <div className="relative">
//         <img
//           className="w-5 h-5 absolute top-4 right-4 cursor-pointer"
//           src="/assets/images/icon-close.svg"
//           alt="close"
//           onClick={() => setUserAuth({ type: "register", open: false })}
//         />

//         <div>
//           <img
//             className="w-full rounded-t-xl"
//             src="/assets/images/forget-password-header.png"
//             alt="forget-password"
//           />
//         </div>

//         <div className="px-8 pb-4">
//           <div>
//             <h3 className="text-3xl font-medium my-1">忘记密码</h3>
//           </div>
//           <form onSubmit={handleSubmit} className="w-full mt-8">
//             <Input
//               label="用户名"
//               name="username"
//               placeholder="请输入用户名"
//               type="text"
//               className="shadow mb-2"
//               addonBeforeIcon={
//                 <Select options={areaCodes} value={""} onChange={() => {}} />
//               }
//             />
//             <div className="flex items-center">
//               <Input
//                 // label="用户名"
//                 name="verificationCode"
//                 placeholder="请输入验证码"
//                 type="text"
//                 className="shadow mb-2 mr-2"
//               />
//               <button
//                 type="submit"
//                 className="bg-primary text-white px-2 py-2 rounded-lg w-1/2 cursor-pointer text-xs mb-2"
//               >
//                 获取验证码
//               </button>
//             </div>
//             <Input
//               label="密码"
//               name="password"
//               placeholder="请输入密码"
//               type={showPassword ? "text" : "password"}
//               className="shadow mb-2"
//               icon={
//                 <img
//                   className="w-5 h-[18px]"
//                   src="/assets/images/icon-lock-outline.svg"
//                   alt="lock"
//                 />
//               }
//               addonAfterIcon={
//                 <img
//                   className="w-5 h-[18px]"
//                   src={
//                     showPassword
//                       ? "/assets/images/icon-hide-eye.svg"
//                       : "/assets/images/icon-show-eye.svg"
//                   }
//                   alt="left"
//                   onClick={() => setShowPassword((prev) => !prev)}
//                 />
//               }
//             />
//             <Input
//               label="确认密码"
//               name="confirmPassword"
//               placeholder="请输入确认密码"
//               type={showPassword ? "text" : "password"}
//               className="shadow mb-2"
//               icon={
//                 <img
//                   className="w-5 h-[18px]"
//                   src="/assets/images/icon-lock-outline.svg"
//                   alt="lock"
//                 />
//               }
//               addonAfterIcon={
//                 <img
//                   className="w-5 h-[18px]"
//                   src={
//                     showPassword
//                       ? "/assets/images/icon-hide-eye.svg"
//                       : "/assets/images/icon-show-eye.svg"
//                   }
//                   alt="left"
//                   onClick={() => setShowPassword((prev) => !prev)}
//                 />
//               }
//             />

//             <button
//               type="submit"
//               className="bg-primary text-white px-10 py-2 rounded-lg w-full cursor-pointer mt-8"
//             >
//               找回密码
//             </button>
//             <div className="flex justify-center mt-6">
//               <span
//                 className="text-[#367AFF]"
//                 onClick={() => setUserAuth({ type: "login", open: true })}
//               >
//                 返回登录
//               </span>
//             </div>
//           </form>
//         </div>
//       </div>
//     </Modal>
//   );
// };

// export default ForgetPassword;
